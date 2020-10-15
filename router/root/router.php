#!/usr/bin/php
<?php

define("SYSTEM_PANEL", getenv("SYSTEM_PANEL"));



/**
 * Send docker api request
 */
function dockerApi($url, $m = "GET", $post = null)
{
	$content = "";
	$cmd = "/usr/bin/curl -s -X " . $m . " -H 'Content-Type: application/json' ";
	if ($post != null) $cmd .= "-d '" . $post . "' ";
	$cmd .= "--unix-socket /var/run/docker.sock http:/v1.39" . $url;
	$content = @shell_exec($cmd . " 2>/dev/null");
	return $content;
}



/**
 * Get services from docker socket
 */
function get_services()
{
	$res = [];
	$content = dockerApi("/services");
	$services = json_decode($content, true);
	
	foreach ($services as $service)
	{
		$service_id = $service["ID"];
		$service_name = $service["Spec"]["Name"];
		$res[$service_name] = [];
		
		$tasks_str = dockerApi("/tasks?filters=" . urlencode('{"service":{"' . $service_name . '":true}}'));
		$tasks = json_decode($tasks_str, true);
		
		foreach ($tasks as $task)
		{
			$state = $task["Status"]["State"];
			if ($state != "running") continue;
			$networks = $task["NetworksAttachments"];
			
			if ($networks) foreach ($networks as $network)
			{
				$network_name = $network["Network"]["Spec"]["Name"];
				if ($network_name != "router") continue;
				
				$addresses = $network["Addresses"];
				foreach ($addresses as $address)
				{
					$ip = explode("/", $address);
					$res[$service_name][] = $ip[0];
				}
			}
		}
		
	}
	
	return $res;
}



/**
 * Update nginx file
 */
function update_nginx_file($file_name, $new_content)
{
	$file = "/etc/nginx/" . $file_name;
	$old_content = "";
	
	if (file_exists($file))
	{
		$old_content = @file_get_contents($file);
	}
	
	$dir_name = dirname($file);
	if (!file_exists($dir_name))
	{
		mkdir($dir_name, 0775, true);
	}
	
	if ($old_content != $new_content)
	{
		file_put_contents($file, $new_content);
		echo "[router.php] Updated nginx file " . $file_name . "\n";
		return true;
	}
	
	return false;
}



/**
 * Delete nginx file
 */
function delete_nginx_file($file_name)
{
	$file = "/etc/nginx/" . $file_name;
	if (file_exists($file))
	{
		unlink($file);
		echo "[router.php] Delete nginx file " . $file_name . "\n";
		return true;
	}
	
	return false;
}



/**
 * Update upstreams
 */
function update_upstreams($services)
{
	$new_content = "";
	
	foreach ($services as $service_name => $ips)
	{
		if (count($ips) == 0) continue;
		
		$new_content .= "upstream ${service_name}.test {\n";
		foreach ($ips as $ip){
			$new_content .= "\tserver ${ip};\n";
		}
		$new_content .= "}\n";
		
	}
	
	return update_nginx_file("conf.d/99-upstreams.conf", $new_content);
}



/**
 * Reload nginx
 */
function nginx_reload()
{
	echo "[router.php] Nginx reload\n";
	$s = shell_exec("/usr/sbin/nginx -s reload");
	echo "[router.php] " . $s;
}


$res = false;

$services = get_services($services);
$res = $res | update_upstreams($services);


/* Reload nginx */
if ($res)
{
	nginx_reload();
}
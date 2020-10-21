#!/usr/bin/php
<?php

define("CLOUD_PANEL", getenv("CLOUD_PANEL"));
define("CLOUD_DOMAIN", getenv("CLOUD_DOMAIN"));



/**
 * Returns ip from nslookup
 */
function nslookup($domain)
{
	if ($domain == "") return [];
	$cmd = "nslookup ${domain} 127.0.0.11 |grep Address | awk '{print $2}'";
	$str = shell_exec($cmd);
	$arr = explode("\n", $str);
	$arr[0] = "";
	$arr = array_filter($arr, function($ip){ return $ip != ""; });
	return $arr;
}



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
 * Returns curl
 */
function curl($url, $data)
{
	$curl = curl_init();
	$opt = [
		CURLOPT_URL => $url,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_FOLLOWLOCATION => 10,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => [
			"data" => json_encode($data)
		],
	];
	curl_setopt_array($curl, $opt);
	return $curl;
}



/**
 * Send api request
 */
function send_api($url, $data)
{
	$curl = curl($url, $data);
	$out = curl_exec($curl);
	$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	curl_close($curl);
	
	$response = null;
	$code = (int)$code;
	if ($code == 200 || $code == 204)
	{
		$response = @json_decode($out, true);
	}
	else if ($code == 400 || $code == 404)
	{
		$response = @json_decode($out, true);
	}
	
	if ($response != null && isset($response["code"]) && $response["code"] == 1)
	{
		return $response["response"];
	}
	
	return null;
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
	
	/* Can't remove main cloud domain */
	if ($file == "/etc/nginx/domains/" . CLOUD_DOMAIN . ".conf" )
	{
		echo "[router.php] nginx file " . $file_name . " can not be deleted\n";
		return false;
	}
	
	/* Can't services admins. Only clear */
	if ($file == "/etc/nginx/inc/services_admin_page.inc")
	{
		file_put_contents($file, "");
		echo "[router.php] Clear nginx file " . $file_name . "\n";
		return true;
	}
	
	if (file_exists($file))
	{
		unlink($file);
		echo "[router.php] Delete nginx file " . $file_name . "\n";
		return true;
	}
	
	return false;
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



/**
 * Get services from docker socket
 */
function get_services_from_docker_socket()
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
			$desired_state = $task["DesiredState"];
			if ($state != "running") continue;
			if ($desired_state != "running") continue;
			$networks = $task["NetworksAttachments"];
			
			if ($networks) foreach ($networks as $network)
			{
				$network_name = $network["Network"]["Spec"]["Name"];
				if ($network_name != "cloud_router") continue;
				
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
 * Update upstreams
 */
function update_upstreams($services)
{
	$new_content = "";
	
	foreach ($services as $service_name => $ips)
	{
		if (count($ips) == 0) continue;
		
		$new_content .= "upstream ${service_name}.test {\n";
		foreach ($ips as $ip)
		{
			$new_content .= "\tserver ${ip};\n";
		}
		$new_content .= "}\n";
		
		$new_content .= "upstream ${service_name}.admin.test {\n";
		foreach ($ips as $ip)
		{
			$new_content .= "\tserver ${ip}:81;\n";
		}
		$new_content .= "}\n";
		
	}
	
	return update_nginx_file("conf.d/99-upstreams.conf", $new_content);
}



/**
 * Get nginx files
 */
function get_nginx_changes($timestamp)
{
	$url = "http://".CLOUD_PANEL."/api/self/Bayrell.CloudOS.Balancer/default/getNginxChanges/";
	$data = [
		"timestamp" => $timestamp,
	];
	
	return send_api($url, $data);
}



/**
 * Update nginx
 */
function update_nginx_files()
{
	$res = false;
	
	$timestamp = 0;
	if (file_exists("/data/nginx.changes.last"))
	{
		$timestamp = file_get_contents("/data/nginx.changes.last");
	}
	
	$files = get_nginx_changes($timestamp);
	if ($files != null) foreach ($files as $file)
	{
		$file_name = $file["name"];
		$enable = $file["enable"];
		$is_deleted = $file["is_deleted"];
		$content = $file["content"];
		
		/* Change file */
		if ($enable == 1 && $is_deleted == 0)
		{
			$res = $res | update_nginx_file($file_name, $content);
		}
		
		/* Delete file */
		else
		{
			$res = $res | delete_nginx_file($file_name);
		}
		
	}
	
	file_put_contents("/data/nginx.changes.last", time());
	
	return $res;
}


$res = false;

/* Update upstreams */
$services = get_services_from_docker_socket($services);
$res = $res | update_upstreams($services);

/* Update nginx files */
$res = $res | update_nginx_files();

/* Reload nginx */
if ($res)
{
	nginx_reload();
}
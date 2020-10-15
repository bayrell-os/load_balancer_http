#!/usr/bin/php
<?php

define("SYSTEM_PANEL", getenv("SYSTEM_PANEL"));


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

function array_equal($arr1, $arr2)
{
	$res1 = array_diff($arr1, $arr2);
	$res2 = array_diff($arr2, $arr1);
	return count($res1) == 0 && count($res2) == 0;
}


function get_service_ip($services, $service_name)
{
	$ips = nslookup($service_name);
	if (!isset($services[$service_name])) $services[$service_name] = [];
	if (array_equal($services[$service_name], $ips)) return $services;
	$services[$service_name] = $ips;
	return $services;
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
	
	update_nginx_file("conf.d/99-upstreams.conf", $new_content);
	
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
 * Returns curl
 */
function curl($url, $data)
{
	$curl = curl_init();
	$otp = [
		CURLOPT_URL => $url,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_FOLLOWLOCATION => 10,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
		CURLOPT_POSTFIELDS => [
			"data" => json_encode($data)
		],
	];
	curl_setopt_array($curl, $otp);
	return $curl;
}



/**
 * Get services
 */
function get_services($services)
{
	$url = "http://".SYSTEM_PANEL."/api/self/Bayrell.CloudOS.Balancer/default/getServices/";
	$data = [];
	
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
		$arr = $response["response"];
		foreach ($arr as $item)
		{
			$name = isset($item["name"]) ? $item["name"] : "";
			$ip = isset($item["ip"]) ? $item["ip"] : null;
			
			if ($ip == null || count($ip) == 0) continue;
			if ($name == SYSTEM_PANEL) continue;
			if ($name == "") continue;
			
			$ip = array_map
			(
				function ($s)
				{
					$arr = explode("/", $s);
					return $arr[0];
				},
				$ip
			);
			
			$services[$name] = $ip;
		}
	}
	
	return $services;
}



/**
 * Get nginx files
 */
function get_nginx_files($timestamp)
{
	$url = "http://".SYSTEM_PANEL."/api/self/Bayrell.CloudOS.Balancer/default/getNginxChanges/";
	$data = [
		"timestamp" => $timestamp,
	];
	
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
	
	return [];
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
	
	$files = get_nginx_files($timestamp);
	foreach ($files as $file)
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


/* Get services */
$services = get_service_ip($services, SYSTEM_PANEL);
$services = get_services($services);
$res = update_upstreams($services);
$res = update_nginx_files();

/* Reload nginx */
if ($res)
{
	nginx_reload();
}

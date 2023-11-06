#!/usr/bin/php
<?php

define("CLOUD_OS_KEY", getenv("CLOUD_OS_KEY"));
define("CLOUD_OS_GATEWAY", getenv("CLOUD_OS_GATEWAY"));


/**
 * Returns curl
 */
function curl($url, $data)
{
	$time = time();
	$key = CLOUD_OS_KEY;
	$arr = array_keys($data); sort($arr);
	array_push($arr, $time);
	$text = implode("|", $arr);
	$sign = hash_hmac("SHA512", $text, $key);
	
	$curl = curl_init();
	$opt =
	[
		CURLOPT_URL => $url,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_FOLLOWLOCATION => 10,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER =>
		[
			"Content-Type: application/json",
		],
		CURLOPT_POSTFIELDS => json_encode
		(
			[
				"data" => $data,
				"time" => $time,
				"sign" => $sign,
				"alg"  => "sha512",
			]
		),
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
	
	//var_dump($out);
	//var_dump($url);
	//var_dump($data);
	//var_dump($code);
	
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
		return $response["data"];
	}
	
	return null;
}


/**
 * Update nginx file
 */
function update_nginx_file($file_name, $new_content)
{
	$file = "/data/nginx/" . $file_name;
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
	
	if ($old_content != $new_content || !file_exists($file) && $new_content == "")
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
 * Reload nginx
 */
function nginx_reload()
{
	echo "[router.php] Nginx reload\n";
	$s = shell_exec("/usr/sbin/nginx -s reload");
	echo "[router.php] " . $s;
}


/**
 * Get nginx files
 */
function get_nginx_changes($timestamp)
{
	$url = "http://" . CLOUD_OS_GATEWAY . "/api/bus/nginx/changes/";
	$data =
	[
		"timestamp" => $timestamp,
	];
	return send_api($url, $data);
}


/**
 * Get ssl changes
 */
function get_ssl_changes($timestamp)
{
	$url = "http://" . CLOUD_OS_GATEWAY . "/api/bus/ssl/get_changes/";
	$data =
	[
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
		$timestamp = (int)file_get_contents("/data/nginx.changes.last");
	}
	
	$files = get_nginx_changes($timestamp);
	if ($files != null)
	{
		foreach ($files as $file)
		{
			$file_name = $file["name"];
			$enable = $file["enable"];
			$is_deleted = $file["is_deleted"];
			$content = $file["content"];
			
			/* Change file */
			if ($enable == 1 && $is_deleted == 0)
			{
				if (update_nginx_file($file_name, $content)) $res = true;
			}
			
			/* Delete file */
			else
			{
				if (delete_nginx_file($file_name)) $res = true;
			}
		}
		
		file_put_contents("/data/nginx.changes.last", time());
	}
	/*
	$groups = get_ssl_changes($timestamp);
	if ($groups != null)
	{
		foreach ($groups as $group)
		{
			$group_name = $group["id"];
			$public_key = $group["public_key"];
			$private_key = $group["private_key"];
			
			$public_key_path = "/ssl/" . $group_name . "/public.key";
			$private_key_path = "/ssl/" . $group_name . "/private.key";
			
			if (update_nginx_file($public_key_path, $public_key)) $res = true;
			if (update_nginx_file($private_key_path, $private_key)) $res = true;
		}
	}
	*/
	
	return $res;
}


$res = false;

/* Update nginx files */
if (update_nginx_files()) $res = true;

/* Reload nginx */
if ($res)
{
	nginx_reload();
}
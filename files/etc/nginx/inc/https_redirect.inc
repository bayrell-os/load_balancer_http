if ($ssl_protocol = ""){
	rewrite ^/(.*) https://$server_name/$1 permanent;
}
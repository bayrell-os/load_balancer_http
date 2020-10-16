#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPT_PATH=`dirname $SCRIPT`

docker stop cloud_router
docker rm cloud_router
docker volume create cloud_router_data

docker run --name cloud_router -d \
	--network="router" \
	--restart=always \
	--log-driver=journald \
	-e "DNS_RESOLVER=127.0.0.11" \
	-e "SYSTEM_PANEL=cloud_os_system_panel" \
	-e "SYSTEM_DOMAIN=cloud_os.local" \
	-v "cloud_router_data:/data" \
	-v "/var/run/docker.sock:/var/run/docker.sock:ro" \
	-v "${SCRIPT_PATH}/router/root/router.php:/root/router.php" \
	-p 80:80 -p 443:443 \
	bayrell/cloud_router:20201015_160215

docker ps
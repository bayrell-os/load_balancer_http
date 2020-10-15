#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPT_PATH=`dirname $SCRIPT`

docker stop cloud_router
docker rm cloud_router

docker run --name cloud_router -d \
	--network="router" \
	--restart=always \
	--log-driver=journald \
	-e "CLOUD_OS_EDITION=simple" \
	-e "DNS_RESOLVER=127.0.0.11" \
	-e "SYSTEM_PANEL=simple_os_system_panel" \
	-e "SYSTEM_DOMAIN=simple_os.local" \
	-v "cloud_router_data:/data" \
	-v "/var/run/docker.sock:/var/run/docker.sock:ro" \
	-v "${SCRIPT_PATH}/router/root/router.php:/root/router.php" \
	-p 80:80 -p 443:443 \
	bayrell/cloud_router:20201015_160215

docker ps
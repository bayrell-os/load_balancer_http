#!/bin/bash

docker network create --subnet 172.21.0.1/16 --driver=overlay --attachable load_balancer -o "com.docker.network.bridge.name"="load_balancer"

docker network create --subnet 172.22.0.1/16 --driver=overlay --attachable cloud_admin -o "com.docker.network.bridge.name"="cloud_admin"


sleep 2

docker network ls


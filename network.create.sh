#!/bin/bash

docker network create --subnet 172.21.0.1/16 --driver=overlay --attachable cloud_router -o "com.docker.network.bridge.name"="cloud_router"


sleep 2

docker network ls


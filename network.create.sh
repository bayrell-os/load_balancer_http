#!/bin/bash

docker network create --subnet 172.21.0.1/16 --driver=overlay --attachable cloud_frontend -o "com.docker.network.bridge.name"="cloud_frontend"

docker network create --subnet 172.22.0.1/16 --driver=overlay --attachable cloud_backend -o "com.docker.network.bridge.name"="cloud_backend"


sleep 2

docker network ls


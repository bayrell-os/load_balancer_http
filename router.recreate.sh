#!/bin/bash

docker service rm cloud_router
sleep 10
docker stack deploy -c router.yaml cloud --with-registry-auth

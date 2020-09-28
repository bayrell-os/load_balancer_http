#!/bin/bash

docker service rm balancer_cloud_router
sleep 10
docker stack deploy -c cloud_router.yaml balancer --with-registry-auth

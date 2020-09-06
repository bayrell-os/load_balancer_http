#!/bin/bash

docker service rm dev_cloud_router
sleep 10
docker stack deploy -c cloud_router.yaml dev --with-registry-auth

#!/bin/bash

docker stack deploy -c cloud_router.yaml balancer --with-registry-auth

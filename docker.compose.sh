#!/bin/bash

docker stack deploy -c cloud_router.yaml dev --with-registry-auth

#!/bin/bash

docker stack deploy -c router.yaml cloud --with-registry-auth

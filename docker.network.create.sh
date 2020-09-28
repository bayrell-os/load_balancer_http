#!/bin/bash

docker network create --subnet 10.100.0.0/16 --driver=overlay --attachable balancer

sleep 2

docker network ls


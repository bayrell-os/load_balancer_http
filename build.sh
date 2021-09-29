#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPT_PATH=`dirname $SCRIPT`
BASE_PATH=`dirname $SCRIPT_PATH`

RETVAL=0
VERSION=0.4.0
TAG=`date '+%Y%m%d_%H%M%S'`

case "$1" in
	
	test)
		docker build ./ -t bayrell/load_balancer_http:$VERSION-$TAG --file Dockerfile
		cd ..
	;;
	
	amd64)
		docker build ./ -t bayrell/load_balancer_http:$VERSION-amd64 --file Dockerfile --build-arg ARCH=-amd64
	;;
	
	arm64v8)
		docker build ./ -t bayrell/load_balancer_http:$VERSION-arm64v8 --file Dockerfile --build-arg ARCH=-arm64v8
	;;
	
	arm32v7)
		docker build ./ -t bayrell/load_balancer_http:$VERSION-arm32v7 --file Dockerfile --build-arg ARCH=-arm32v7
	;;
	
	manifest)
		rm -rf ~/.docker/manifests/docker.io_load_balancer_http-*
		
		docker push bayrell/load_balancer_http:$VERSION-amd64
		docker push bayrell/load_balancer_http:$VERSION-arm64v8
		docker push bayrell/load_balancer_http:$VERSION-arm32v7
		
		docker manifest create --amend bayrell/load_balancer_http:$VERSION \
			bayrell/load_balancer_http:$VERSION-amd64 \
			bayrell/load_balancer_http:$VERSION-arm64v8 \
			bayrell/load_balancer_http:$VERSION-arm32v7
		docker manifest push --purge bayrell/load_balancer_http:$VERSION
	;;
	
	all)
		$0 amd64
		$0 arm64v8
		$0 arm32v7
		$0 manifest
	;;
	
	*)
		echo "Usage: $0 {amd64|arm64v8|arm32v7|manifest|all|test}"
		RETVAL=1

esac

exit $RETVAL
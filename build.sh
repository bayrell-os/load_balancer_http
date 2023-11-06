#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPT_PATH=`dirname $SCRIPT`
BASE_PATH=`dirname $SCRIPT_PATH`

RETVAL=0
VERSION=0.5.0
IMAGE="bayrell/load_balancer_http"
TAG=`date '+%Y%m%d_%H%M%S'`

case "$1" in
	
	test)
		echo "Build $IMAGE:$VERSION-$TAG"
		docker build ./ -t $IMAGE:$VERSION-$TAG --file Dockerfile
		docker tag $IMAGE:$VERSION-$TAG $IMAGE:$VERSION
		cd ..
	;;
	
	amd64)
		export DOCKER_DEFAULT_PLATFORM=linux/amd64
		docker build ./ -t $IMAGE:$VERSION-amd64 --file Dockerfile --build-arg ARCH=-amd64
	;;
	
	arm64v8)
		export DOCKER_DEFAULT_PLATFORM=linux/arm64/v8
		docker build ./ -t $IMAGE:$VERSION-arm64v8 --file Dockerfile --build-arg ARCH=-arm64v8
	;;
	
	manifest)
		rm -rf ~/.docker/manifests/docker.io_load_balancer_http-*
		
		docker push $IMAGE:$VERSION-amd64
		docker push $IMAGE:$VERSION-arm64v8
		
		docker manifest create --amend $IMAGE:$VERSION \
			$IMAGE:$VERSION-amd64 \
			$IMAGE:$VERSION-arm64v8
		docker manifest push --purge $IMAGE:$VERSION
	;;
	
	all)
		$0 amd64
		$0 arm64v8
		$0 manifest
	;;
	
	*)
		echo "Usage: $0 {amd64|arm64v8|manifest|all|test}"
		RETVAL=1

esac

exit $RETVAL
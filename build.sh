#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPT_PATH=`dirname $SCRIPT`
BASE_PATH=`dirname $SCRIPT_PATH`

RETVAL=0
VERSION=0.1.0
TAG=`date '+%Y%m%d_%H%M%S'`

case "$1" in
	
	test)
		docker build ./ -t bayrell/cloud_router_http:$VERSION-$TAG --file Dockerfile
		cd ..
	;;
	
	amd64)
		docker build ./ -t bayrell/cloud_router_http:$VERSION-amd64 --file Dockerfile --build-arg ARCH=-amd64
		docker push bayrell/cloud_router_http:$VERSION-amd64
	;;
	
	arm32v7)
		docker build ./ -t bayrell/cloud_router_http:$VERSION-arm32v7 --file Dockerfile --build-arg ARCH=-arm32v7
		docker push bayrell/cloud_router_http:$VERSION-arm32v7
	;;
	
	manifest)
		docker manifest create --amend bayrell/cloud_router_http:$VERSION \
			bayrell/cloud_router_http:$VERSION-amd64 \
			bayrell/cloud_router_http:$VERSION-arm32v7
		docker manifest push --purge bayrell/cloud_router_http:$VERSION
	;;
	
	all)
		$0 amd64
		$0 arm32v7
		$0 manifest
	;;
	
	*)
		echo "Usage: $0 {amd64|arm32v7|manifest|all|test}"
		RETVAL=1

esac

exit $RETVAL
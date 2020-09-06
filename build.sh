#!/bin/bash

SCRIPT=$(readlink -f $0)
SCRIPT_PATH=`dirname $SCRIPT`
BASE_PATH=`dirname $SCRIPT_PATH`

RETVAL=0
TAG=`date '+%Y%m%d_%H%M%S'`

case "$1" in
	
	cloud_router)
		docker build ./ -t bayrell/cloud_router:$TAG --file docker/router.dockerfile
		docker tag bayrell/cloud_router:$TAG bayrell/cloud_router:latest
		cd ..
	;;
	
	*)
		echo "Usage: $0 {cloud_router}"
		RETVAL=1

esac

exit $RETVAL
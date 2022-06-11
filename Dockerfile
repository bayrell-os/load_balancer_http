ARG ARCH=
FROM bayrell/alpine_php_fpm:7.4${ARCH}

RUN apk update && apk add dnsmasq php7-curl curl && rm -rf /var/cache/apk/*

COPY files /
RUN cd ~; \
	rm /var/www/html/index.php; \
	rm /etc/supervisor.d/php-fpm.ini; \
	chmod +x /root/router.php; \
	chmod +x /root/run.sh; \
	echo "Ok"

if [ ! -d /data/nginx ]; then
    mkdir -p /data/nginx
fi

if [ ! -d /data/nginx/conf.d ]; then
    mkdir -p /data/nginx/conf.d
fi

if [ ! -d /data/nginx/domains ]; then
    mkdir -p /data/nginx/domains
fi

if [ ! -d /data/nginx/ssl ]; then
    mkdir -p /data/nginx/ssl
fi

cp -rf /etc/nginx/conf.d/* /data/nginx/conf.d

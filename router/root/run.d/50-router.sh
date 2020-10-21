echo "0" > /data/nginx.changes.last

if [ ! -z $CLOUD_PANEL ] && [ ! -z $CLOUD_DOMAIN ]; then
	sed -i "s|%CLOUD_PANEL%|${CLOUD_PANEL}|g" /etc/nginx/conf.d/99-upstreams.conf
	sed -i "s|%CLOUD_PANEL%|${CLOUD_PANEL}|g" /etc/nginx/sites-available/50-system-panel.conf
	sed -i "s|%CLOUD_DOMAIN%|${CLOUD_DOMAIN}|g" /etc/nginx/sites-available/50-system-panel.conf
	ln -s ../sites-available/50-system-panel.conf /etc/nginx/domains/${CLOUD_DOMAIN}.conf
fi
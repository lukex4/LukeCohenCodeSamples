#!/bin/sh

#
# SOLTWP node reboot script, will be run whenever a SOLTWP node is restarted
#

#
#
# Flush iptables and ensure our custom rules are loaded
#
iptables-restore < /etc/iptables/soltwp.rules

#
#
# Start PHP-FPM and nginx services
#
service php5-fpm start
service nginx start

#
#
# Pull SOLTWP themes and plugins
#
WPURL=`cat /wpurl`

cd "/webserver/domains/$WPURL/httpdocs/wp-content/themes" && sudo -u www-data git reset --hard && sudo -u www-data git pull

cd "/webserver/domains/$WPURL/httpdocs/wp-content/plugins" && sudo -u www-data git reset --hard && sudo -u www-data git pull

#
#
# Start the watcher script with nohup
#
cd /solt-server-scripts/wpdelvery && nohup sh watcher.sh &

#
#
# Complete
#
exit 0
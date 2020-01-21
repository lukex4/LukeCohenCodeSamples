#!/bin/sh

###
#
# Watches for git pull trigger files and then executes accordingly.
#
###

while true
do

  DT=`date "+%Y%m%d%H%M%S"`
  WPURL=`cat /wpurl`

  # 1. WP themes
  FILE='/webserver/wpthemeshook'

  if [ -f $FILE ]; then

    cd "/webserver/domains/$WPURL/httpdocs/wp-content/themes" && sudo -u www-data git reset --hard && sudo -u www-data git pull

    echo "solt-wp-themes pulled at $DATE_WITH_TIME"

    rm -rf $FILE

    sleep 1s

  fi

  # 2. WP plugins
  FILE='/webserver/wppluginshook'

  if [ -f $FILE ]; then

    cd "/webserver/domains/$WPURL/httpdocs/wp-content/plugins" && sudo -u www-data git reset --hard && sudo -u www-data git pull

    echo "solt-wp-plugins pulled at $DATE_WITH_TIME"

    rm -rf $FILE

    sleep 1s

  fi

  sleep 2s

done
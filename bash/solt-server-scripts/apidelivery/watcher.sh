#!/bin/sh

###
#
# Watches for git pull trigger files and then executes accordingly.
#
###

while true
do

  DT=`date "+%Y%m%d%H%M%S"`

  # 1. Update to SOLTAPI-Node
  FILE='/webserver/nodeapihook'

  if [ -f $FILE ]; then

    forever stopall

    cd /nodeserver/oltapi/dist && sudo -u www-data git reset --hard && sudo -u www-data git pull

    forever start /nodeserver/oltapi/dist/app.js

    echo "oltapi-node pulled at $DATE_WITH_TIME"

    rm -rf $FILE

    sleep 1s

  fi

  sleep 2s

done
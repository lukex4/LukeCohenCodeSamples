#!/bin/bash

# This is a refactoring of the original DDOS Deflate installation script, which is quite frankly baffling. This approach also relies on local source, config, and ban files, to ensure nothing unknown is getting installed through the backdoor.

if [ -d '/usr/local/ddos' ]; then
	echo; echo; echo "Please un-install the previous version first"
	exit 0
else
	mkdir -p /usr/local/ddos
fi

# Get conf file, license file, ignore.ip.list file, and the main ddos.sh sourcefile

sudo cp /ddos-install/ddos.conf /usr/local/ddos/

sudo cp /ddos-install/LICENSE /usr/local/ddos/

sudo cp /ddos-install/ignore.ip.list /usr/local/ddos/

sudo cp /ddos-install/ddos.sh /usr/local/ddos/

chmod 0755 /usr/local/ddos/ddos.sh

# Create symlink in sbin
cp -s /usr/local/ddos/ddos.sh /usr/local/sbin/ddos

# Create cron to run DDOS Deflate every minute
echo -n 'Creating cron to run script every minute.....(Default setting)'
/usr/local/ddos/ddos.sh --cron > /dev/null 2>&1

touch /opt/ddos_deflate.installed && echo "DDOS Deflate Installed" > /opt/ddos_deflate.installed

# COMPLETE

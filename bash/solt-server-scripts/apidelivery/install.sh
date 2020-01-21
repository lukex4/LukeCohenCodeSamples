#!/bin/sh

#
# Installs a SOLT API (Node) delivery server to specifications.
#

apt-get update
apt-get install -y git-core


#
#
# Create git user and SSH keys so we can access SOLT GitHub
#
adduser git --disabled-password --gecos "First Last,RoomNumber,WorkPhone,HomePhone"

mkdir -p /home/git/.ssh

#GIT_SSH_KEY_PRIV=`cat /solt-server-scripts/wpdelivery/templates/ssh-git-id_rsa.tpl`
#GIT_SSH_KEY_PUB=`cat /solt-server-scripts/wpdelivery/templates/ssh-git-id_rsa.pub.tpl`

echo "-----BEGIN RSA PRIVATE KEY-----
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
-----END RSA PRIVATE KEY-----
" > /home/git/.ssh/id_rsa
echo "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQCaGnmAmmO9JOQS+dk+CRMKdkcTXfgcb1PJOjbuGDL45Suo3L955ItZP5oc+0Z6FUu1u1KlD9S5VsppSohB4AZXQk2J4fHkj2jG68o74H27EYDIamXbGWxVOq49tedLqhIEgEFKEGVy30bBsZZ6mnOS1+xHRwAB4pnh8ISkXL54KkGWnrhcl9xllcPQdrMbzTEiIr0zI6aTFYWlKYplJDTewg/33wiX02YfIcjQhYtz4DQU9WzRM/77HsQpzcQO9WrhL2GFJYiErfTHaXie1gtmjvhVTfzTsT+N7ZnUDSk2zW00GFP30QJkigND6Ly81CeHc9K/IiI86wvCLQhNUfz4FJrHkLZaCqFeZd4vhHkNvjI9YiLyyl4BifOgTjri3wASvaMfVBKDU4MlZ0qMR92UzgFUZwzPz/aeq9ZxZr/GUCb0NChE/yOvVbUmIyHVCS0/XUM3kqjG/KhwyrHeZmGjZ97t4qeNXHxWbcIK8DeCbZ1tNYMXnsEQdHd48kN1huSPxJFg+7ZKtbTE2k2kxwZ/7WhYQHSXe6wLl4g4ycjpoiwr6XP97ep3ohK8gzZyE+io606G5t7Lfxjajo72Kp6KJ3oJX0/CWeOI7Z2XZ41HpIcwuePydxo0meEJt/5jOwRjVom6RqF44o4Mdm5DVzLlfxy1cBT9jdJJTQlb/mx7lw== admin@delivery-3.16to25live.co.uk
" > /home/git/.ssh/id_rsa.pub

chmod 600 /home/git/.ssh/id_rsa
chmod 600 /home/git/.ssh/id_rsa.pub

chown -R git:git /home/git
usermod -d /home/git git


#
#
# A few little tweaks to ensure we can connect to GitHub successfully
#
echo "Host github.com
   Hostname github.com
   StrictHostKeyChecking no
   IdentityFile /home/git/.ssh/id_rsa" > /home/git/.ssh/config

ssh-keyscan -H github.com >> /home/git/.ssh/known_hosts

chown git:git /home/git/.ssh/config
chown git:git /home/git/.ssh/known_hosts

echo "StrictHostKeyChecking no" > /etc/ssh/ssh_config


#
#
# Download various scripts from GitHub
#
cd /
mkdir -p solt-server-scripts
chown git:git /solt-server-scripts
cd /solt-server-scripts && sudo -u git git clone git@github.com:SOLT-UKT/solt-server-scripts.git . && chmod u+x *


#
#
# Place the reboot script in /etc/rc.local
#
echo "#!/bin/sh -e

/solt-server-scripts/apidelivery/update.sh
/solt-server-scripts/apidelivery/reboot.sh

exit 0" > /etc/rc.local


#
#
# Install cURL and Python Pip
#
apt-get install -y curl libcurl3 python-pip


#
#
# Install AWS-CLI with Pip
#
pip install awscli


#
#
# AWS access credentials
#
AWS_ACCESS_KEY_ID=AKIAJRJBNRTKQ7J3HXWZ
AWS_SECRET_ACCESS_KEY=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
AWS_DEFAULT_REGION=eu-west-2

export AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID
export AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY
export AWS_DEFAULT_REGION=$AWS_DEFAULT_REGION


#
#
# Fetch the name of this instance from AWS
#
cd / && wget http://169.254.169.254/latest/meta-data/instance-id
INSTANCE_ID=`cat /instance-id`

aws ec2 describe-tags --region eu-west-2 --filters "Name=resource-id,Values=$INSTANCE_ID" | grep -2 Name | grep Value | tr -d ' ' | cut -f2 -d: | tr -d '"' | tr -d ',' | cat > /instance-name



#
#
# Set some variables for this server, using command line variables provided when the script is executed.
#
SERVER_NAME=`cat /instance-name`
SERVER_TLD="$SERVER_NAME.16to25live.co.uk"


#
#
# Change server hostname
#
hostname $SERVER_TLD
echo "$SERVER_NAME" > "/$SERVER_NAME"


#
#
# Set iptables rules
#
mkdir -p /etc/iptables

IPTABLES_RULES=`cat /solt-server-scripts/apidelivery/templates/iptables-rules.tpl`
echo "$IPTABLES_RULES" > /etc/iptables/soltapi.rules


#
#
# Load SOLT-API custom firewall rules into iptables
#
iptables --flush
iptables-restore < /etc/iptables/soltapi.rules


#
#
# Persist iptables rules so they survive a restart
#
echo "#!/bin/sh
/sbin/iptables-restore < /etc/iptables/soltapi.rules" > /etc/network/if-pre-up.d/iptables

chmod +x /etc/network/if-pre-up.d/iptables && chown root:root /etc/network/if-pre-up.d/iptables


#
#
# Create a RAM swapfile if it doesn't already exist
#
FILE='/var/swap.img'
if [ -f $FILE ]; then
 echo "There's already a swapfile."
 swapon /var/swap.img
else
 echo "Creating a swapfile."

 cd /var && touch swap.img && chmod 600 /var/swap.img
 dd if=/dev/zero of=/var/swap.img bs=1024k count=1000
 mkswap /var/swap.img
 swapon /var/swap.img

fi


#
#
# Set custom SSH daemon rules
#
SSHD_CONFIG=`cat /solt-server-scripts/apidelivery/templates/sshd-config.tpl`
echo "$SSHD_CONFIG" > /etc/ssh/sshd_config

chmod 644 /etc/ssh/sshd_config
service ssh restart && service sshd restart


#
#
# Install Node, NPM, and Forever
#
apt-get install -y nodejs
apt-get install -y build-essential
apt-get install -y npm

npm install forever -g
npm install n -g && n latest


#
#
# Create symlink between /usr/bin/nodejs and /usr/bin/node (to enable 'nodejs' to be run on command line)
#
sudo ln -s /usr/bin/nodejs /usr/bin/node


#
#
# Install git
#
apt-get install -y git-core


#
#
# Create home directory for www-data
#
mkdir -p /home/www-data/.ssh


#
#
# SSH keys for www-data, and SSH config so we can pull from SOLT-UKT git repository
#
WWWDATA_SSH_KEY_PRIV=`cat /solt-server-scripts/apidelivery/templates/ssh-wwwdata-id_rsa.tpl`
WWWDATA_SSH_KEY_PUB=`cat /solt-server-scripts/apidelivery/templates/ssh-wwwdata-id_rsa.pub.tpl`

echo "$WWWDATA_SSH_KEY_PRIV" > /home/www-data/.ssh/id_rsa
echo "$WWWDATA_SSH_KEY_PUB" > /home/www-data/.ssh/id_rsa.pub

#
#
# Set ownership and permissions on the www-data SSH key
#
chmod 600 /home/www-data/.ssh/id_rsa
chmod 600 /home/www-data/.ssh/id_rsa.pub

chown -R www-data:www-data /home/www-data


#
#
# Tell Debian where the new home directory is for the www-data user
#
usermod -d /home/www-data www-data


#
#
# Create the 'config' file for www-data's and root's SSH sessions
#
echo "Host github.com
   Hostname github.com
   StrictHostKeyChecking no
   IdentityFile /home/www-data/.ssh/id_rsa" > /home/www-data/.ssh/config

ssh-keyscan -H github.com >> /home/www-data/.ssh/known_hosts

chown www-data:www-data /home/www-data/.ssh/config
chown www-data:www-data /home/www-data/.ssh/known_hosts


#
#
# Create space for the Node OLT-API application to live
#
mkdir -p /nodeserver/oltapi
chown -R www-data:www-data /nodeserver


#
#
# Git clone OLT-API Node.js application
#
cd /nodeserver/oltapi && sudo -u www-data git clone git@github.com:SOLT-UKT/oltapi-node.git dist && cd /nodeserver/oltapi/dist
chown -R www-data:www-data /nodeserver


#
#
# Install Redis
#
apt-get install -y redis-server
service redis-server start


#
#
# Install Redis Node client
#
cd /nodeserver/oltapi/dist && npm install -g redis --save
cd /nodeserver/oltapi/dist && npm install


#
#
# Tell forever to run OLT-API Node
#
forever stopall
TZ='Europe/London' forever start /nodeserver/oltapi/dist/app.js


#
#
# Install nginx and PHP
#
apt-get install -y nginx

apt-get install -y php5-fpm php5-mysql php5-gd php5-curl php5-common php5-cli php5-dev php5-readline php-pear mysql-client pwgen apache2-utils

service nginx stop
service php5-fpm stop


#
#
# Configure PHP-FPM
#
NGINX_FASTCGI=`cat /solt-server-scripts/apidelivery/templates/nginx-fastcgi-vars.tpl`
echo "$NGINX_FASTCGI" > /etc/nginx/fastcgi.conf


#
#
# PHP-FPM global config
#
PHPFPM_GLOBAL=`cat /solt-server-scripts/apidelivery/templates/phpfpm-global.tpl`
echo "$PHPFPM_GLOBAL" > /etc/php5/fpm/php-fpm.conf


#
#
# PHP-FPM pool config
#
PHPFPM_POOL=`cat /solt-server-scripts/apidelivery/templates/phpfpm-pool.tpl`
echo "$PHPFPM_POOL" > /etc/php5/fpm/pool.d/www.conf


#
#
# nginx global config
#
NGINX_GLOBAL=`cat /solt-server-scripts/apidelivery/templates/nginx-conf.tpl`
echo "$NGINX_GLOBAL" > /etc/nginx/nginx.conf


#
#
# Create the /webserver directory and set appropriate ownership and permissions
#
cd /
mkdir -p /webserver/domains
chown www-data:www-data /webserver -R
chmod 755 /webserver -R


#
#
# Create the basic landing page config for the subdomain attached to this server
#
NGINX_SITE_SERVER=$(eval "echo \"$(cat /solt-server-scripts/apidelivery/templates/nginx-site-hooks.tpl)\"")
echo "$NGINX_SITE_SERVER" > "/etc/nginx/sites-available/$SERVER_TLD"


#
#
# Re-create nginx sites-available/sites-enabled symlinks
#
rm -rf /etc/nginx/sites-available/default
rm -rf /etc/nginx/sites-enabled/default
ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled


#
#
# Create the landing page for this server
#
mkdir -p "/webserver/domains/$SERVER_TLD/httpdocs"

echo "<h1>$SERVER_TLD</h1>
<h5>soltapi node</h5>" > "/webserver/domains/$SERVER_TLD/httpdocs/index.php"

chown -R www-data:www-data "/webserver/domains/$SERVER_TLD"


#
#
# Create git hook receivers
#

echo "<?php

\$filePath = '/webserver/nodeapihook';
file_put_contents(\$filePath, '');

?>" > "/webserver/domains/$SERVER_TLD/httpdocs/git-nodeapi-hook.php"


chown www-data:www-data "/webserver/domains/$SERVER_TLD/" -R


#
#
# Start PHP-FPM and nginx
#
service nginx start
service php5-fpm start


#
#
# Start the watcher script using nohup
#
cd /solt-server-scripts/apidelivery && nohup sh watcher.sh &


#
#
# Complete
#
echo "installation complete" > /.install
exit 0

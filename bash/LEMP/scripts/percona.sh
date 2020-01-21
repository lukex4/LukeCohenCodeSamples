#!/bin/bash
#   Author: krishna - and thanks to all the helpful folks on SO.
#   date: apr-03-2018
#   script
#   Notes: unattented script to install percona server. Pieced together from all over SO.
#           see versions below next to each item. they are current as on this script creation dates.
#           2. percona server 5.7 or later
#           4. ubuntu 64bit 16.4.3
#
#       This script works for me but may not be the best bash script, Please help, to make it better.
#
#   you have to run this script as sudo
#   if you saved this script as let's say install-percona.sh
#   then do this
#   chmod +x install-percona.sh
#   sudo ./install-percona.sh
#

#   do everything in downloads
mkdir -p ~/downloads
cd ~/downloads

#   Variables
#       Passwords are like under garments.
#           1. Dont leave them lying around
#           2. Change Often
#           3. Do not share it with others
#           4. Dont be chaep. Invest in STRONG AND high quality one's.
#   So I hope you know what to do with the next 3 lines.
MYSQL_ROOT_PASSWORD="p2381obHPv3OVh3"
DB_USERNAME="crazy_dbu"
DB_PASSWORD="p2381obHPv3OVh3"

export DEBIAN_FRONTEND=noninteractive

sudo apt-get install -y debconf-utils

#   Install software
#   We are going to install Percona Server.
#
#   By default, percona-server is going to ask for the root password and we automate that with debconf-set-selections
#   begin percona stuff
#       you need to change the next 2 lines, visit the percona server site to get latest values.
wget https://repo.percona.com/apt/percona-release_0.1-4.$(lsb_release -sc)_all.deb
dpkg -i percona-release_0.1-4.$(lsb_release -sc)_all.deb
#
apt-get update

#   also change the version number accordingly. the -5.7 part
#   Note. its root-pass and re-root-pass unlike root_password and root_password_again for other flavors of mysql.
#
#   !!!!! CAUTION !!!!
#   Be sure to check /var/cache/debconf/passwords.dat file for these 2 entries. After installation is completed.
#   The value fields were clear in my case but check anyway.
#   Y.M.M.V - dont want to leave passwords hanging in the clear.
#
#
echo "percona-server-server-5.7 percona-server-server-5.7/root-pass password ${MYSQL_ROOT_PASSWORD}" | debconf-set-selections
echo "percona-server-server-5.7 percona-server-server-5.7/re-root-pass password ${MYSQL_ROOT_PASSWORD}" | debconf-set-selections
apt install -y percona-server-server-5.7

#   Configure MySQL ie Percona
#   We are going to create a user also and the 3 CREATE FUNCTION lines are what percona suggests.
#   these may happen silently

echo "lets configure percona."

mysql -u root -p${MYSQL_ROOT_PASSWORD} <<EOF
CREATE FUNCTION fnv1a_64 RETURNS INTEGER SONAME 'libfnv1a_udf.so';
CREATE FUNCTION fnv_64 RETURNS INTEGER SONAME 'libfnv_udf.so';
CREATE FUNCTION murmur_hash RETURNS INTEGER SONAME 'libmurmur_udf.so';
CREATE USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL ON *.* TO '${DB_USERNAME}'@'localhost' with grant option;
FLUSH Privileges;
EOF

echo "make sure you run mysql_secure_installation after install"
#   run the secure installation script
#mysql_secure_installation
echo "done."

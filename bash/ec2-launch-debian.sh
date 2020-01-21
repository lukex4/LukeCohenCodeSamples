#/bin/bash

#
# Relevant updates, and install required packages
#
apt-get update

apt-get install -y locales locales-all apt-utils wget apt-transport-https lsb-release ca-certificates software-properties-common ruby

#
# PHP 7.x
#
wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && sh -c 'echo "deb https://packages.sury.org/php/ stretch main" > /etc/apt/sources.list.d/php.list'

apt-get update && apt-get install -y apache2 php7.2 php7.2-cli libapache2-mod-php7.2 php-apcu php-xdebug php7.2-gd php7.2-json php7.2-ldap php7.2-mbstring php7.2-mysql php7.2-xml php7.2-xsl php7.2-zip php7.2-soap php7.2-curl php7.2-opcache nano git

#
# AWS CodeDeploy agent
#
wget https://bucket-name.s3.amazonaws.com/latest/install
chmod +x install
./install auto

#
# Configure Apache
#
mkdir -p /var/www/public
touch /var/www/public/index.html
chown www-data:www-data /var/www -R

echo '<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/public
    <Directory /var/www/public>
        AllowOverride All
        Require all granted
    </Directory>
    # LogLevel info ssl:warn
    # ErrorLog /dev/stdout
    # CustomLog /dev/stdout combined
    # Include conf-available/serve-cgi-bin.conf
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

a2dismod mpm_prefork
a2dismod mpm_event
a2enmod rewrite
a2enmod php7.2
service apache2 stop
service apache2 start

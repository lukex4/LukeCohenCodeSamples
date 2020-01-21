#
# A basic Debian-based Dockerfile, primarily designed to quickly get Laravel applications running, but can be used with most LAMP apps (TODO: replace Apache with nginx).
# Use in conjunction with docker-compose.yml and apache.conf, in the same directory.
#


### Slim Debian-Jessie, because Jessie is more stable than Stretch
### Set the base user as root, to cover bases with folder permissions, et al.

FROM debian:jessie-slim
USER root


### Language environment vars

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8
ENV LANGUAGE C.UTF-8


### Apt update, and install some basics

RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y locales locales-all apt-utils wget apt-transport-https lsb-release ca-certificates software-properties-common


### Install the custom apt repository for PHP7.x

RUN DEBIAN_FRONTEND=noninteractive wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && sh -c 'echo "deb https://packages.sury.org/php/ jessie main" > /etc/apt/sources.list.d/php.list'


### Update apt for the new repository, and install PHP 7.x

RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    apache2 \
    php7.1 \
    php7.1-cli \
    libapache2-mod-php7.1 \
    php-apcu \
    php-xdebug \
    php7.1-gd \
    php7.1-json \
    php7.1-ldap \
    php7.1-mbstring \
    php7.1-mysql \
    php7.1-xml \
    php7.1-xsl \
    php7.1-zip \
    php7.1-soap \
    php7.1-curl \
    php7.1-opcache \
    #composer \
    nano


### Keep this image light, baby

RUN apt-get clean


### Start Apache, set the root folder, etc.

COPY ./apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite
EXPOSE 80

RUN mkdir -p /var/www
COPY . /var/www

RUN chown -R www-data:www-data /var/www

CMD /usr/sbin/apache2ctl -D FOREGROUND
WORKDIR /var/www

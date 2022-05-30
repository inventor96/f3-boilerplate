#!/bin/bash

# we need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

# install required stuff that doesn't come with the image
apt-get update -yqq > /dev/null
apt-get install git libonig-dev zip unzip libzip-dev mariadb-client -yqq > /dev/null

# install php requirements
docker-php-ext-install pdo_mysql zip > /dev/null

# setup and install composer
curl -sS https://getcomposer.org/installer | php
php composer.phar install

# use testing config
ln app/config/config.gitlabci.php app/config/config.php

# reset settings
echo "" >> /usr/local/etc/php/php.ini-development
echo "mbstring.internal_encoding =" >> /usr/local/etc/php/php.ini-development
ln /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini
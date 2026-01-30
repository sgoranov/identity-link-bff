#!/usr/bin/env bash

# Modify /etc/hosts
DEFAULT_ROUTE=$(ip route show default | awk '/default/ {print $3}')
echo "$DEFAULT_ROUTE localhost.container.com" >> /etc/hosts

cd /var/www/

# Run composer install and replace the correct configuration
# rm -rf vendor
composer install --no-scripts

# Set correct permissions on var/
rm -rf var/cache/*
chown -R www-data:www-data var

# This will exec the CMD from Dockerfile
exec "$@"
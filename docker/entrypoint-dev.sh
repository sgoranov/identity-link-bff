#!/usr/bin/env bash
set -e

cd /app

composer install --no-interaction --no-scripts --no-progress
php bin/console cache:clear

exec "$@"
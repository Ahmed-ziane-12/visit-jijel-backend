#!/bin/sh
set -e

php artisan config:cache
php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisord.conf

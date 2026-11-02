#!/bin/sh
set -e
cd /var/www/html

# Render fournit $PORT ; Apache doit y écouter.
PORT="${PORT:-10000}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force
if [ "${RUN_SEED:-false}" = "true" ]; then
    php artisan db:seed --force
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground

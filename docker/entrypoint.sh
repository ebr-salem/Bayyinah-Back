#!/bin/sh
set -e

cd /var/www/html

# If commands are passed (e.g. `docker compose run app php artisan migrate`),
# execute them directly instead of starting the web server.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# Container role: "worker" runs the queue listener, "app" runs web (default)
ROLE="${CONTAINER_ROLE:-app}"

# Ensure writable storage paths
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data
chown -R www-data:www-data storage bootstrap/cache

if [ "$ROLE" = "worker" ]; then
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=90
fi

# Cache Laravel config/routes in production for performance
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache || true
    php artisan route:cache  || true
    php artisan view:cache  || true
fi

# Start PHP-FPM in the background, keep nginx in the foreground (PID 1)
php-fpm -D

exec nginx -g 'daemon off;'
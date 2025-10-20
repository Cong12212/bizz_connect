#!/usr/bin/env sh
set -e
php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan migrate --force || true
php artisan l5-swagger:generate --quiet || true
exec /entrypoint supervisord

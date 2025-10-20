#!/usr/bin/env sh
set -e

# Clear cache, storage link
php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Rebuild cache
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run migrations (ignore error if DB not ready yet)
php artisan migrate --force || true

# Generate Swagger docs if L5-Swagger exists
php artisan l5-swagger:generate --quiet || true

# Cuối cùng: KHỞI ĐỘNG lại PHP-FPM + Nginx qua supervisord
exec /entrypoint supervisord

#!/usr/bin/env sh
set -e

# --- chuẩn hoá app khi container start ---
php artisan storage:link || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Migrate DB nếu biến môi trường đã set
php artisan migrate --force || true

# Nếu có L5-Swagger thì generate; nếu không có package thì bỏ qua (không fail)
php artisan l5-swagger:generate --quiet || true

# Gọi lại entrypoint gốc của image để start php-fpm + nginx (qua supervisord)
exec /entrypoint supervisord

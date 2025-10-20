#!/usr/bin/env sh
set -e

# Dọn cache & link storage (không lỗi nếu đã có)
php artisan storage:link || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Build cache mới
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Chạy migrate (nếu DB env đúng sẽ tạo bảng)
php artisan migrate --force || true

# Nếu bạn dùng L5-Swagger (có package), generate docs
php artisan l5-swagger:generate --quiet || true

# Khởi động nginx + php-fpm qua supervisord
exec /usr/bin/supervisord -n

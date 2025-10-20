# Nginx + PHP-FPM + nhiều PHP ext sẵn (có pdo_mysql)
FROM webdevops/php-nginx:8.3-alpine

# Web root Laravel
ENV WEB_DOCUMENT_ROOT=/app/public
# Bật opcache cho production
ENV PHP_OPCACHE=1

# Làm việc trong /app
WORKDIR /app

# 1) Cài vendor sớm để cache layer tốt
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# 2) Copy toàn bộ mã nguồn
COPY . .

# Tối ưu autoload + quyền thư mục
RUN composer dump-autoload -o \
    && mkdir -p storage bootstrap/cache storage/logs \
    && chown -R application:application storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Chạy dưới user không phải root
USER application
# (Base image đã tự start nginx + php-fpm → không cần CMD)

FROM webdevops/php-nginx:8.3-alpine

# Laravel chạy qua Nginx + PHP-FPM
ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_OPCACHE=1
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Copy toàn bộ mã nguồn (phải gồm: artisan, composer.json, public/, ...)
COPY . .

# Cài vendor sau khi đã có artisan để post-scripts chạy OK
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && mkdir -p storage bootstrap/cache storage/logs \
    && chown -R application:application storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint: chạy các lệnh chuẩn hoá + sinh swagger (nếu có)
COPY docker/entrypoint.sh /usr/local/bin/app-entry.sh
RUN chmod +x /usr/local/bin/app-entry.sh

# Chạy dưới user không phải root
USER application

# Khởi động container thông qua script của mình (sau đó gọi lại entrypoint gốc của image)
CMD ["/usr/local/bin/app-entry.sh"]

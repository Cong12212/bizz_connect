FROM webdevops/php-nginx:8.3-alpine

ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_OPCACHE=1
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
COPY . .

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && mkdir -p storage bootstrap/cache storage/logs \
    && chown -R application:application storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# thêm entrypoint tự chạy migrate/swagger
COPY docker/entrypoint.sh /usr/local/bin/app-entry.sh
RUN chmod +x /usr/local/bin/app-entry.sh

# dùng entrypoint của mình (chạy supervisord trực tiếp)
ENTRYPOINT ["/usr/local/bin/app-entry.sh"]

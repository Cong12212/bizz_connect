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

# NOTE: KHÔNG đặt USER, KHÔNG override ENTRYPOINT/CMD

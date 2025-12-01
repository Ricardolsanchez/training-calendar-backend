FROM webdevops/php-nginx:8.2-alpine

WORKDIR /app

COPY . /app

RUN chown -R application:application storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

RUN composer install \
    --no-dev \
    --optimize-autoloader

RUN php artisan migrate --force || true
FROM webdevops/php-nginx:8.2-alpine

WORKDIR /app

COPY . /app

# 👇 NUEVO: dar permisos de escritura a Laravel
RUN chown -R application:application storage bootstrap/cache

RUN composer install \
    --no-dev \
    --optimize-autoloader

RUN php artisan migrate --force || true
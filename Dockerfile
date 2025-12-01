FROM webdevops/php-nginx:8.2-alpine

WORKDIR /app

COPY . /app

RUN composer install \
    --no-dev \
    --optimize-autoloader

# Cache Laravel config, routes, views

# Run migrations automatically
RUN php artisan migrate --force || true
FROM webdevops/php-nginx:8.2-alpine

WORKDIR /app

COPY . /app

RUN composer install \
    --no-dev \
    --optimize-autoloader

# Cache Laravel config, routes, views
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache || true

# Run migrations automatically
RUN php artisan migrate --force || true
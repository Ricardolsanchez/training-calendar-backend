# Imagen con Nginx + PHP 8.2 ya configurados
FROM webdevops/php-nginx:8.2-alpine

# Carpeta de trabajo
WORKDIR /app

# Copiar todo el proyecto
COPY . /app

# Instalar dependencias de PHP en modo producción
RUN composer install \
    --no-dev \
    --optimize-autoloader

# Optimizar Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache || true

# Directorio público de Laravel
ENV WEB_DOCUMENT_ROOT=/app/public

# Puerto HTTP
EXPOSE 80

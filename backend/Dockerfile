FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libsqlite3-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql zip mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN cp -n .env.example .env || true \
    && composer install --no-dev --optimize-autoloader \
    && mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 8000
CMD php artisan key:generate --force && php artisan migrate --seed --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

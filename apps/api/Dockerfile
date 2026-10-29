# syntax=docker/dockerfile:1
FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install -j2 pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
CMD ["php", "artisan", "serve", "--no-reload", "--host=0.0.0.0", "--port=8000"]

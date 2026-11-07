# syntax=docker/dockerfile:1
ARG PHP_IMAGE=php:8.4-fpm-bookworm
FROM ${PHP_IMAGE} AS php-base
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip gosu \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 bcmath exif gd intl mbstring pcntl pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN mkdir -p app database/factories database/seeders \
    && composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress \
    && composer check-platform-reqs --no-dev

FROM php-base AS source
COPY . .
RUN chmod -R a+rX app bootstrap config database public resources routes docker \
    && chmod a+r artisan composer.json composer.lock \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && ln -sfn /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

FROM node:22-bookworm-slim AS assets
ENV NODE_OPTIONS=--max-old-space-size=512
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY --from=source /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM source AS app
COPY --from=assets /build/public/build ./public/build
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-dadu.ini
COPY docker/php/pool.conf /usr/local/etc/php-fpm.d/zz-dadu.conf
COPY docker/scripts/entrypoint.sh /usr/local/bin/dadu-entrypoint
RUN chmod 755 /usr/local/bin/dadu-entrypoint \
    && chmod 644 /usr/local/etc/php/conf.d/zz-dadu.ini /usr/local/etc/php-fpm.d/zz-dadu.conf
ENTRYPOINT ["dadu-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN chmod 644 /etc/nginx/conf.d/default.conf \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
EXPOSE 8080

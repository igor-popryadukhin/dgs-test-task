FROM dunglas/frankenphp:1-php8.4-bookworm AS base

RUN install-php-extensions pdo_pgsql intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY composer.json composer.lock symfony.lock ./
RUN COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

FROM base AS dev
ENV APP_ENV=dev
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

FROM base AS prod
ENV APP_ENV=prod
RUN composer install --no-dev --no-interaction --no-progress --classmap-authoritative \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && php bin/console cache:clear \
    && php bin/console asset-map:compile

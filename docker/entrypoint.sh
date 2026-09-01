#!/bin/sh
set -eu

export COMPOSER_CACHE_DIR=/tmp/composer-cache

composer install --no-interaction --no-progress --prefer-dist
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console importmap:install --no-interaction

exec "$@"

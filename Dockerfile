# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.3-alpine AS frankenphp_upstream

FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# persistent / runtime deps
RUN apk add --no-cache \
    acl \
    file \
    gettext \
    git \
    ;

RUN set -eux; \
    install-php-extensions \
    apcu \
    intl \
    opcache \
    zip \
    ;

COPY --link docker/frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/

COPY --link docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

COPY --from=composer:2 --link /usr/bin/composer /usr/bin/composer

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off
VOLUME /app/var/

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
    install-php-extensions \
    xdebug \
    ;

COPY --link docker/frankenphp/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

# Prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link docker/frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/
COPY --link docker/frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

# prevent the reinstallation of vendors at every changes in the source code
COPY --link composer.json composer.lock symfony.lock ./
RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress;

# copy sources
COPY --link . ./
RUN rm -rf docker/

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    chmod +x bin/console; sync;

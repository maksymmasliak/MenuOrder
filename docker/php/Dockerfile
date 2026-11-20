# Dev-образ PHP-FPM для MenuOrder (app і cron). Параметри приходять з .env через compose.yaml.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm

# curl уже вбудований в офіційний образ, окремо не ставиться.
ARG PHP_EXTENSIONS="gd opcache pdo_mysql"
ARG INSTALL_XDEBUG=0
ARG PUID=1000
ARG PGID=1000

# supercronic — cron для контейнерів (сервіс cron). Нова версія:
# github.com/aptible/supercronic/releases, sha1 — з опису релізу.
ARG SUPERCRONIC_VERSION=v0.2.51
ARG SUPERCRONIC_SHA1SUM=1692066e350a88ff4d7d6474fe3c7a6c41ec5e89

# Встановлювач розширень: сам ставить і потім чистить системні -dev бібліотеки.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# git + unzip потрібні Composer для завантаження пакетів, curl — для supercronic.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions ${PHP_EXTENSIONS} @composer-2 \
    && if [ "${INSTALL_XDEBUG}" = "1" ]; then install-php-extensions xdebug; fi

# Лише amd64. Для arm64 — інший файл і sha1 з того самого релізу.
RUN curl -fsSL -o /usr/local/bin/supercronic \
        "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-amd64" \
    && echo "${SUPERCRONIC_SHA1SUM}  /usr/local/bin/supercronic" | sha1sum -c - \
    && chmod +x /usr/local/bin/supercronic

# Dev-налаштування PHP (display_errors=On тощо) + наші доповнення.
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY conf.d/ /usr/local/etc/php/conf.d/

ENV COMPOSER_HOME=/var/www/.composer \
    COMPOSER_CACHE_DIR=/var/www/.composer/cache \
    COMPOSER_MEMORY_LIMIT=-1

# www-data отримує твій UID/GID: файли в ./app лишаються твоїми, без sudo chown.
RUN groupmod -o -g ${PGID} www-data \
    && usermod -o -u ${PUID} -g ${PGID} www-data \
    && mkdir -p /var/www/.composer/cache \
    && chown -R www-data:www-data /var/www

WORKDIR /var/www/html
USER www-data

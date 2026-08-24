FROM php:8.4.5-fpm AS base

WORKDIR /var/www/app

COPY --from=mlocati/php-extension-installer:2.7.33 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_pgsql intl opcache amqp

COPY php/php.ini /usr/local/etc/php/conf.d/php.ini
COPY php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

FROM base AS vendor_dev

WORKDIR /app

COPY app/composer.json app/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts


FROM base AS vendor_prod

WORKDIR /app

COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

FROM base AS dev

RUN install-php-extensions xdebug
COPY php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY --from=vendor_dev /app/vendor /var/www/app/vendor


FROM base AS prod

COPY app /var/www/app
COPY --from=vendor_prod /app/vendor /var/www/app/vendor
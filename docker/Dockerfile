FROM php:8.4.15-alpine

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

WORKDIR /app

RUN apk add --no-cache \
        libzip-dev \
        zip \
    && docker-php-ext-install zip

ENV PATH="/root/.composer/vendor/bin:/root/.config/composer/vendor/bin:${PATH}"

RUN composer global require humbug/box
RUN composer global require phpacker/phpacker


FROM php:8-fpm

RUN pecl install redis \
    && docker-php-ext-enable redis

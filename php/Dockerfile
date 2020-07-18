ARG PHP_VERSION=""
FROM php:${PHP_VERSION:+${PHP_VERSION}-}fpm-alpine

RUN apk update; \
    apk upgrade;

RUN docker-php-ext-install mysqli
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html/
#need to work on cronjob
CMD while /usr/local/bin/php index.php >> /var/www/html/script.log; do sleep 10; done
FROM php:8.3-fpm-alpine

ENV DEBIAN_FRONTEND=noninteractive

WORKDIR /var/www/html

COPY ./docker/fpm/php.ini /usr/local/etc/php/php.ini
COPY composer.json /var/www/html/
COPY composer.lock /var/www/html/
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY ./index.php /var/www/html/index.php
COPY ./src/config /var/www/html/src/config/

EXPOSE 8000

RUN composer install --no-interaction

RUN sed -i 's|listen = .*|listen = 0.0.0.0:8000|' /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's|listen = .*|listen = 0.0.0.0:8000|' /usr/local/etc/php-fpm.d/www.conf.default \
 && sed -i 's|listen = .*|listen = 8000|' /usr/local/etc/php-fpm.d/zz-docker.conf

RUN composer dump-autoload

CMD ["php-fpm", "--nodaemonize", "-R"]
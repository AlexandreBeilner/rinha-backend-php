FROM php:8.3-cli-alpine

ENV DEBIAN_FRONTEND=noninteractive

RUN apk add --no-cache curl

WORKDIR /app

COPY composer.json /app/
COPY composer.lock /app/
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY ./docker/cli/php.ini /usr/local/etc/php/php.ini
COPY ./src/ /app/src/

RUN composer install --no-interaction

CMD ["php", "/app/src/queue/index.php"]
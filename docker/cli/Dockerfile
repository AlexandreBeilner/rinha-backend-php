FROM php:8.3-cli-alpine

ENV DEBIAN_FRONTEND=noninteractive

RUN apk add --no-cache curl

COPY composer.json /app/
COPY composer.lock /app/
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN composer install --no-interaction

COPY ./src/ /app/src/

CMD ["php", "/app/src/queue/index.php"]
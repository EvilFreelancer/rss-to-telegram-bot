FROM php:8.2-alpine

ENV RSSREADER_TIMEOUT=3600
ENV RSSREADER_CACHE_DIR=/app/cache

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

RUN set -xe \
 && apk add --no-cache --update bash curl libxml2-dev \
 && docker-php-ext-install xml \
 && docker-php-ext-enable xml

COPY . .

RUN set -xe \
 && composer install \
 && chown nobody:nogroup . -R

ENTRYPOINT ["/app/entrypoint.sh"]

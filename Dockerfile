FROM php:8.1-alpine

ENV TS_TIMEOUT=600
ENV RSSREADER_CACHE_DIR=/app/cache

WORKDIR /app

RUN set -xe \
 && apk add --no-cache --update bash curl

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY . .

RUN set -xe \
 && composer install \
 && chown nobody:nogroup . -R

ENTRYPOINT ["/app/entrypoint.sh"]

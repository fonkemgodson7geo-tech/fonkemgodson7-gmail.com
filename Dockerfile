FROM php:8.3-cli-alpine

RUN apk add --no-cache --virtual .build-deps \
        autoconf \
        g++ \
        gcc \
        make \
        pkgconf \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite \
    && apk del .build-deps

WORKDIR /app
COPY . /app

RUN mkdir -p /app/database /app/uploads \
    && chmod -R 775 /app/database /app/uploads \
    && chmod +x /app/scripts/start.sh

EXPOSE 10000

CMD ["sh", "scripts/start.sh"]

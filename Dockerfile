FROM php:8.3-cli-alpine

# Runtime deps: file/libmagic for finfo MIME detection (photo uploads)
RUN apk add --no-cache file

# Build pdo_sqlite (needed for SQLite DB support)
RUN apk add --no-cache --virtual .build-deps \
        autoconf \
        g++ \
        gcc \
        make \
        pkgconf \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite \
    && apk del .build-deps

WORKDIR /app
COPY . /app

RUN mkdir -p /app/database /app/uploads /app/uploads/photos \
    && chmod -R 775 /app/database /app/uploads \
    && chmod +x /app/scripts/start.sh

EXPOSE 10000

CMD ["sh", "scripts/start.sh"]

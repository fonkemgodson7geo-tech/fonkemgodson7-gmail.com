FROM php:8.3-cli-alpine

RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql

WORKDIR /app
COPY . /app

RUN mkdir -p /app/database /app/uploads \
    && chmod -R 775 /app/database /app/uploads \
    && chmod +x /app/scripts/start.sh

EXPOSE 10000

CMD ["sh", "scripts/start.sh"]

FROM composer:2 AS composer

WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-bookworm-slim AS frontend

WORKDIR /app
COPY --from=composer /app /app

ARG VITE_REVERB_APP_KEY=dormida-work-key
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm ci && npm run build

FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        gettext-base \
        libicu-dev \
        libzip-dev \
        nginx \
        supervisor \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY deploy/render/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY deploy/render/supervisord.conf /etc/supervisor/conf.d/dormida.conf
COPY deploy/render/entrypoint.sh /usr/local/bin/render-entrypoint

RUN chmod +x /usr/local/bin/render-entrypoint \
    && rm -f /etc/nginx/sites-enabled/default

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/render-entrypoint"]

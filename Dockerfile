# Symfony 6.4 · PHP 8.2 (matches composer.json platform.php)
FROM php:8.2-apache-bookworm

SHELL ["/bin/bash", "-euo", "pipefail", "-c"]

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
        gd \
        mbstring \
        xml \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'realpath_cache_size=4096K'; \
    echo 'realpath_cache_ttl=600'; \
  } > /usr/local/etc/php/conf.d/opcache-recommended.ini \
  && echo 'date.timezone=UTC' > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist \
    && composer clear-cache

COPY . .

# .dockerignore excludes secrets; Symfony Runtime still expects a readable `.env` path.
# Real values must come from Render (or `docker run -e`); exported env overrides these lines.
RUN printf '%s\n' \
    '# Container defaults only. Set secrets via environment variables on Render.' \
    'APP_ENV=prod' \
    'APP_DEBUG=0' \
    > .env

# Refresh autoload + metadata after full tree; scripts optional (may need runtime env).
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist \
    && composer dump-autoload --classmap-authoritative --no-dev \
    && composer run-script post-install-cmd --no-dev --no-interaction || true

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

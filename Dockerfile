FROM composer:2 AS deps
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --ignore-platform-reqs

FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite mbstring \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/app/public
RUN sed -ri 's!/var/www/html!/var/www/html/app/public!g' /etc/apache2/sites-available/000-default.conf \
    && a2enmod headers \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/security-extra.conf
WORKDIR /var/www/html
COPY --from=deps /build/vendor ./vendor
COPY app ./app
COPY docker/entrypoint.sh /usr/local/bin/comments-entrypoint
RUN mkdir -p /data app/protected/runtime && chown -R www-data:www-data /data app/protected/runtime \
    && chmod +x /usr/local/bin/comments-entrypoint
ENTRYPOINT ["comments-entrypoint"]
CMD ["apache2-foreground"]

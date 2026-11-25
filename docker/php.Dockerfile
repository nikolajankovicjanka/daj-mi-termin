FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev libpng-dev libxml2-dev unzip git \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql bcmath zip gd mbstring dom simplexml xmlreader xmlwriter opcache \
    && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-enabled/termin.conf
RUN printf 'upload_max_filesize=8M\npost_max_size=12M\n' > /usr/local/etc/php/conf.d/uploads.ini
COPY backend/ ./
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
    && php artisan storage:link && chown -R www-data:www-data storage bootstrap/cache

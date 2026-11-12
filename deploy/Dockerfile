FROM php:8.5-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 mysqli pdo_mysql mbstring intl zip gd \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts \
    && mkdir -p application/cache application/logs assets/userImage assets/uploads /var/lib/mapos/storage /var/backups/mapos \
    && chown -R www-data:www-data application/cache application/logs assets/userImage assets/uploads /var/lib/mapos /var/backups/mapos
COPY deploy/apache.conf /etc/apache2/conf-available/mapos.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/mapos.ini
RUN a2enconf mapos

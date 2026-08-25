FROM php:8.3-apache

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 exif gd intl mbstring pdo_mysql zip \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/waymark-server-name.conf \
    && a2enconf waymark-server-name

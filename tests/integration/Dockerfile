FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev unzip curl mariadb-client \
 && docker-php-ext-install pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*
RUN a2enmod headers rewrite
WORKDIR /var/www/html

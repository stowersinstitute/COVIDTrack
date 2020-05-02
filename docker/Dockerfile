# start with the official Composer image and name it
FROM composer AS composer
# continue with the official PHP image
FROM php:7.4-fpm
# copy the Composer PHAR from the Composer image into the PHP image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Prep for downloading Yarn
RUN apt-get update && apt-get install -y gnupg

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

RUN apt-get update && apt-get install -y \
        libpng-dev \
        libzip-dev \
        libpq-dev \
        libldap-dev \
        nodejs \
        yarn \
    && docker-php-ext-install -j$(nproc) iconv \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-install -j$(nproc) sockets \
    && docker-php-ext-install -j$(nproc) ldap \
    && docker-php-ext-install pdo pdo_pgsql pgsql pdo_mysql

WORKDIR /app

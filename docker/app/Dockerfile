FROM php:5.6-apache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite && \
apt-get update && \
apt-get install -y unzip libxml2-dev git libc-client-dev libkrb5-dev && \
docker-php-ext-configure imap --with-kerberos --with-imap-ssl && \
docker-php-ext-install mysqli mbstring xml imap
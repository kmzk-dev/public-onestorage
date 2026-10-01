FROM php:8.2-apache

RUN a2enmod rewrite

RUN echo "upload_max_filesize = 64M\npost_max_size = 64M\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

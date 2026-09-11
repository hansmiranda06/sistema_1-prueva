FROM php:8.3-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql && a2enmod rewrite
WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]

FROM php:8.3-apache

# Instalamos tanto mysqli como pdo_mysql para máxima compatibilidad con bases de datos
RUN docker-php-ext-install mysqli pdo_mysql

# Habilitamos el módulo de reescritura de Apache (útil para URLs limpias)
RUN a2enmod rewrite

# Copiamos la aplicación
COPY app/ /var/www/html/

# Unificamos la asignación de permisos para optimizar el tamaño de la imagen
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
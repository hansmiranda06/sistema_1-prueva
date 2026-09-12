FROM php:8.3-apache

# Instalamos mysqli y pdo_mysql para la conexión a MariaDB
RUN docker-php-ext-install mysqli pdo_mysql

# Habilitamos mod_rewrite de Apache
RUN a2enmod rewrite

# CORREGIDO: Copia el archivo directo desde la raíz del repositorio
COPY index.php /var/www/html/

# Configuración de permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]

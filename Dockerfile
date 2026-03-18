FROM php:8.2-apache

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY includes/ /var/www/includes/

RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

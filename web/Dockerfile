FROM php:8.3-apache

RUN docker-php-ext-install mysqli \
    && a2enmod headers \
    && mkdir -p /var/www/uploads /var/www/app \
    && chown www-data:www-data /var/www/uploads \
    && chmod 750 /var/www/uploads

COPY web/php.ini /usr/local/etc/php/conf.d/imagecheck.ini
COPY web/apache.conf /etc/apache2/conf-available/imagecheck.conf
RUN a2enconf imagecheck

COPY public/ /var/www/html/
COPY config/ /var/www/app/config/
COPY src/ /var/www/app/src/
COPY templates/ /var/www/app/templates/

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD curl -fsS http://127.0.0.1/health.php || exit 1

FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    mysql-server \
    php \
    libapache2-mod-php \
    php-mysql \
    php-gd \
    php-mbstring \
    php-xml \
    php-curl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY public/ /var/www/html/
COPY config/ /var/www/imagecheck-config/
COPY database/schema.sql /docker-entrypoint-initdb.d/schema.sql
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod 755 /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

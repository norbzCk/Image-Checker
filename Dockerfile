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

RUN a2enmod rewrite \
    # Remove the default Ubuntu index.html so Apache serves our index.php.
    # Apache's DirectoryIndex lists index.html BEFORE index.php, so without
    # this the default page would be shown instead of the app.
    && rm -f /var/www/html/index.html

COPY public/ /var/www/html/
COPY config/ /var/www/imagecheck-config/
COPY database/schema.sql /docker-entrypoint-initdb.d/schema.sql
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod 755 /var/www/html \
    # Make the MySQL socket directory world-accessible so Apache's
    # www-data user can reach it. Without this the socket dir stays
    # mode 700 owned by mysql and PHP's default 'localhost' connection
    # fails with "Permission denied".
    && mkdir -p /run/mysqld \
    && chmod 755 /run/mysqld

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

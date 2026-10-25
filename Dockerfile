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
    python3 \
    python3-pip \
    python3-venv \
    supervisor \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Create venv for inference
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Install PyTorch CPU first
RUN pip install --no-cache-dir torch --index-url https://download.pytorch.org/whl/cpu

COPY inference/requirements.txt /opt/inference/requirements.txt
RUN pip install --no-cache-dir -r /opt/inference/requirements.txt

RUN a2enmod rewrite headers \
    && rm -f /var/www/html/index.html

# Copy app files
COPY public/ /var/www/html/
COPY src/ /var/www/app/src/
COPY config/ /var/www/imagecheck-config/
COPY templates/ /var/www/app/templates/
COPY inference/ /opt/inference/
COPY database/schema.sql /docker-entrypoint-initdb.d/schema.sql
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/uploads /var/www/html/uploads /var/log/supervisor /models \
    && chown -R www-data:www-data /var/www/uploads /var/www/html/uploads /var/www/app /opt/inference /models \
    && chmod 755 /var/www/html /var/www/uploads \
    && mkdir -p /run/mysqld \
    && chmod 755 /run/mysqld

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

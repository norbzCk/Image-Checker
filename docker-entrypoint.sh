#!/bin/bash
set -Eeuo pipefail

DB_NAME="${IMAGECHECK_DB_NAME:-imagecheck}"
DB_USER="${IMAGECHECK_DB_USER:-imagecheck_user}"
DB_PASSWORD="${IMAGECHECK_DB_PASSWORD:-change_this_password}"

if [ "$DB_PASSWORD" = "change_this_password" ]; then
    echo "ERROR: Set a strong IMAGECHECK_DB_PASSWORD in docker-compose.yml."
    exit 1
fi

echo "Starting MySQL..."
service mysql start

echo "Waiting for MySQL..."
for attempt in $(seq 1 30); do
    if mysqladmin ping --silent; then
        break
    fi
    sleep 2
done

if ! mysqladmin ping --silent; then
    echo "ERROR: MySQL failed to start."
    exit 1
fi

echo "Creating the application database and user..."
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost'
    IDENTIFIED BY '${DB_PASSWORD}';

ALTER USER '${DB_USER}'@'localhost'
    IDENTIFIED BY '${DB_PASSWORD}';

GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.*
    TO '${DB_USER}'@'localhost';

FLUSH PRIVILEGES;
SQL

echo "Preparing database tables..."
mysql -u root "$DB_NAME" < /docker-entrypoint-initdb.d/schema.sql

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

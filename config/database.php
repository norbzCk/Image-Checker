<?php
declare(strict_types=1);

function getDatabaseConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // 127.0.0.1 = TCP connection. 'localhost' would force the Unix socket
    // at /var/run/mysqld/mysqld.sock, which Apache's www-data user cannot
    // reach because the socket directory is owned by mysql with mode 700.
    $host = getenv('IMAGECHECK_DB_HOST') ?: '127.0.0.1';
    $name = getenv('IMAGECHECK_DB_NAME') ?: 'imagecheck';
    $user = getenv('IMAGECHECK_DB_USER') ?: 'imagecheck_user';
    $password = getenv('IMAGECHECK_DB_PASSWORD') ?: '';

    $db = new mysqli($host, $user, $password, $name);
    $db->set_charset('utf8mb4');

    return $db;
}
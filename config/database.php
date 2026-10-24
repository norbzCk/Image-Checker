<?php
declare(strict_types=1);

function getDatabaseConnection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host     = getenv('IMAGECHECK_DB_HOST') ?: 'db';
    $name     = getenv('IMAGECHECK_DB_NAME') ?: 'imagecheck';
    $user     = getenv('IMAGECHECK_DB_USER') ?: 'imagecheck_user';
    $password = getenv('IMAGECHECK_DB_PASSWORD') ?: '';

    $db = new mysqli($host, $user, $password, $name);
    $db->set_charset('utf8mb4');

    return $db;
}

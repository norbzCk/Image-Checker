<?php
declare(strict_types=1);

require_once '/var/www/app/src/bootstrap.php';

/** Serves a stored upload, only to the browser that uploaded it. */
$token = $_COOKIE['ic_owner'] ?? '';
$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token) || !$id) {
    http_response_code(404);
    exit;
}

try {
    $db   = getDatabaseConnection();
    $repo = new AnalysisRepository($db);
    $row  = $repo->find($id, ownerHash($token));
    $db->close();

    if ($row === null) {
        http_response_code(404);
        exit;
    }

    $service = new AnalysisService($repo, InferenceClient::fromEnv(), UPLOAD_DIR);
    $path    = $service->pathFor((string) $row['stored_filename']);

    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $row['mime_type']);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
} catch (Throwable $e) {
    error_log('ImageCheck image error: ' . $e->getMessage());
    http_response_code(500);
}

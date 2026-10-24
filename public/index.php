<?php
declare(strict_types=1);

require_once '/var/www/app/src/bootstrap.php';

$token      = ownerToken();
$owner      = ownerHash($token);
$csrf       = csrfToken($token);
$flash      = '';
$flashType  = 'info';
$current    = null;
$history    = [];
$db         = null;

try {
    $db      = getDatabaseConnection();
    $repo    = new AnalysisRepository($db);
    $service = new AnalysisService($repo, InferenceClient::fromEnv(), UPLOAD_DIR);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!csrfValid($token, $_POST['csrf'] ?? null)) {
                throw new UserError('Your session expired. Reload the page and try again.');
            }

            $action = (string) ($_POST['action'] ?? 'upload');
            $id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if ($action === 'upload') {
                $validated = (new UploadValidator())->validate($_FILES['image'] ?? null);
                $newId     = $service->submit($validated, $owner, clientHash());
                redirect('/?id=' . $newId);
            } elseif ($action === 'retry' && $id) {
                $service->run($id, $owner);
                redirect('/?id=' . $id);
            } elseif ($action === 'delete' && $id) {
                $service->delete($id, $owner);
                redirect('/');
            } else {
                throw new UserError('Unknown action.');
            }
        } catch (UserError $error) {
            http_response_code(422);
            $flash     = $error->getMessage();
            $flashType = 'error';
        } catch (Throwable $error) {
            error_log('ImageCheck error: ' . $error->getMessage());
            http_response_code(500);
            $flash     = 'The upload could not be completed. Check the server logs.';
            $flashType = 'error';
        }
    }

    $requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($requestedId) {
        $current = $repo->find($requestedId, $owner);
        if ($current !== null && $current['signals_json'] !== null) {
            $current['signals'] = json_decode((string) $current['signals_json'], true) ?: [];
        }
    }
    $history = $repo->recent($owner, 10);
} catch (Throwable $error) {
    error_log('ImageCheck page error: ' . $error->getMessage());
    if ($flash === '') {
        $flash     = 'The service is starting up or temporarily unavailable. Please retry shortly.';
        $flashType = 'error';
    }
} finally {
    $db?->close();
}

require '/var/www/app/templates/home.php';

<?php
declare(strict_types=1);

require_once '/var/www/imagecheck-config/database.php';

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please select an image to upload.');
        }

        $image = $_FILES['image'];
        $maxSize = 10 * 1024 * 1024;

        if ($image['size'] <= 0 || $image['size'] > $maxSize) {
            throw new RuntimeException('The image must be smaller than 10 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($image['tmp_name']);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            throw new RuntimeException('Use a JPEG, PNG, WebP, or GIF image.');
        }

        $imageInfo = getimagesize($image['tmp_name']);

        if ($imageInfo === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        $originalName = basename($image['name']);
        $storedName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
        $uploadDirectory = '/var/www/html/uploads';
        $destination = $uploadDirectory . '/' . $storedName;

        if (!move_uploaded_file($image['tmp_name'], $destination)) {
            throw new RuntimeException('The server could not save the image.');
        }

        try {
            $db = getDatabaseConnection();

            $statement = $db->prepare(
                'INSERT INTO image_analyses
                (original_filename, stored_filename, mime_type, file_size)
                VALUES (?, ?, ?, ?)'
            );

            $fileSize = (int) $image['size'];
            $statement->bind_param(
                'sssi',
                $originalName,
                $storedName,
                $mimeType,
                $fileSize
            );

            $statement->execute();
            $statement->close();
            $db->close();
        } catch (Throwable $databaseError) {
            if (is_file($destination)) {
                unlink($destination);
            }

            throw $databaseError;
        }

        $message = 'Image uploaded and saved. AI detection is not available yet because the pretrained model has not been integrated.';
        $messageType = 'success';
    } catch (Throwable $error) {
        error_log('ImageCheck error: ' . $error->getMessage());
        $message = $error instanceof RuntimeException
            ? $error->getMessage()
            : 'The upload could not be completed. Check the server logs.';
        $messageType = 'error';
    }
}

$history = [];

try {
    $db = getDatabaseConnection();
    $result = $db->query(
        'SELECT id, original_filename, file_size, detection_status, created_at
         FROM image_analyses
         ORDER BY id DESC
         LIMIT 10'
    );

    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    $db->close();
} catch (Throwable $error) {
    error_log('ImageCheck history error: ' . $error->getMessage());
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ImageCheck — AI Image Detector</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/">ImageCheck<span>.</span></a>
        <span class="status"><span class="status-dot"></span> Prototype</span>
    </header>

    <main class="container">
        <section class="hero">
            <p class="eyebrow">IMAGE AUTHENTICITY LAB</p>
            <h1>Understand what's<br>behind your image.</h1>
            <p class="subtitle">
                Upload an image to prepare it for AI-generated image analysis.
                Detection results will appear after the model is integrated.
            </p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="notice <?= escapeHtml($messageType) ?>" role="status">
                <?= escapeHtml($message) ?>
            </div>
        <?php endif; ?>

        <section class="upload-card">
            <div class="card-heading">
                <div>
                    <p class="eyebrow">STEP 01</p>
                    <h2>Upload an image</h2>
                </div>
                <span class="file-limit">MAX 10 MB</span>
            </div>

            <form id="upload-form" method="POST" enctype="multipart/form-data">
                <label class="dropzone" for="image">
                    <span class="upload-icon" aria-hidden="true">↑</span>
                    <strong id="file-label">Choose an image to analyse</strong>
                    <span id="file-help">JPEG, PNG, WebP or GIF</span>
                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        required
                    >
                </label>

                <div id="preview-container" hidden>
                    <img id="image-preview" alt="Preview of the selected image">
                </div>

                <button class="submit-button" type="submit">
                    Upload image <span aria-hidden="true">→</span>
                </button>
            </form>

            <div class="model-status">
                <span class="status-dot pending"></span>
                <div>
                    <strong>AI model: not integrated</strong>
                    <p>Uploads are stored, but no authenticity prediction is made.</p>
                </div>
            </div>
        </section>

        <section class="history-section">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">YOUR WORKSPACE</p>
                    <h2>Recent uploads</h2>
                </div>
                <span class="count"><?= count($history) ?> records</span>
            </div>

            <?php if (!$history): ?>
                <div class="empty-state">
                    <p>No images uploaded yet.</p>
                    <span>Your uploaded image records will appear here.</span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td><?= escapeHtml($item['original_filename']) ?></td>
                                <td><?= number_format(((int) $item['file_size']) / 1024, 1) ?> KB</td>
                                <td><span class="pending-label">Pending model</span></td>
                                <td><?= escapeHtml($item['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        ImageCheck · Local image analysis prototype
    </footer>
</body>
</html>
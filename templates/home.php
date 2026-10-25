<?php
declare(strict_types=1);

/**
 * Variables from index.php: $flash, $flashType, $current (row|null), $history, $csrf.
 */

$verdictLabels = [
    'AI_DECLARED' => ['AI-generated (declared by Content Credentials)', 'ai'],
    'LIKELY_AI'   => ['Likely AI-generated', 'ai'],
    'UNCERTAIN'   => ['Uncertain', 'uncertain'],
    'LIKELY_REAL' => ['Likely a real photo', 'real'],
];

function verdictLabel(array $labels, ?string $verdict): array
{
    return $labels[$verdict ?? ''] ?? ['Not analysed', 'uncertain'];
}

function percent(mixed $probability): int
{
    return (int) round(((float) $probability) * 100);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ImageCheck — AI Image Detector</title>
    <link rel="stylesheet" href="/css/style.css">
    <script src="/js/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">ImageCheck<span>.</span></a>
</header>

<main class="container">
    <section class="hero">
        <p class="eyebrow">IMAGE AUTHENTICITY LAB</p>
        <h1>Understand what's<br>behind your image.</h1>
        <p class="subtitle">
            Upload an image to get an estimate of whether it was AI-generated or captured by a camera.
            It is an estimate, not proof.
        </p>
    </section>

    <?php if ($flash !== ''): ?>
        <div class="notice <?= e($flashType) ?>" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($current !== null): ?>
        <?php [$label, $tone] = verdictLabel($verdictLabels, $current['verdict']); ?>
        <section class="result-card" aria-live="polite">
            <div class="card-heading">
                <div>
                    <p class="eyebrow">RESULT</p>
                    <h2><?= e($current['original_filename']) ?></h2>
                </div>
                <span class="file-limit"><?= (int) $current['width'] ?>×<?= (int) $current['height'] ?> px</span>
            </div>

            <div class="result-body">
                <img class="result-image" src="/image.php?id=<?= (int) $current['id'] ?>"
                     alt="Uploaded image <?= e($current['original_filename']) ?>">

                <div class="result-details">
                    <?php if ($current['status'] === 'DONE'): ?>
                        <p class="verdict <?= e($tone) ?>"><?= e($label) ?></p>

                        <label class="meter-label" for="ai-meter">
                            Estimated chance this is AI-generated:
                            <strong><?= percent($current['ai_probability']) ?>%</strong>
                        </label>
                        <progress id="ai-meter" class="meter <?= e($tone) ?>" max="100"
                                  value="<?= percent($current['ai_probability']) ?>"></progress>

                        <?php
                        $c2pa  = $current['signals']['c2pa'] ?? [];
                        $hints = $current['signals']['hints'] ?? [];
                        ?>
                        <dl class="signals">
                            <dt>Content Credentials (C2PA)</dt>
                            <dd>
                                <?php if (($c2pa['present'] ?? null) === true): ?>
                                    Found<?= !empty($c2pa['issuer']) ? ' · signed by ' . e((string) $c2pa['issuer']) : '' ?>
                                    <?= !empty($c2pa['ai_declared']) ? '· declares AI generation' : '· no AI declaration' ?>
                                <?php elseif (($c2pa['present'] ?? null) === false): ?>
                                    None found (common: most platforms strip them)
                                <?php else: ?>
                                    Could not be checked
                                <?php endif; ?>
                            </dd>
                            <dt>Camera metadata (EXIF)</dt>
                            <dd>
                                <?= !empty($hints['camera_tags']) ? 'Camera make/model present' : (!empty($hints['has_exif']) ? 'Some metadata, no camera tags' : 'None') ?>
                                <span class="muted">(weak hint, easily removed or faked)</span>
                            </dd>
                            <dt>Model</dt>
                            <dd><?= e($current['model_name']) ?> · <?= (int) $current['latency_ms'] ?> ms</dd>
                        </dl>

                        <p class="caveat">
                            Detectors make mistakes, especially on screenshots, heavily compressed or edited
                            images, and output from newer generators. A "real" result does not prove an image is
                            authentic.
                            Optional extra check: upload it to Google's free
                            <a href="https://synthid.com" target="_blank" rel="noopener noreferrer">SynthID Detector</a>
                            (it only finds watermarks from Google and partner tools; "not detected" proves nothing).
                        </p>
                    <?php elseif ($current['status'] === 'FAILED'): ?>
                        <p class="verdict uncertain">Analysis failed</p>
                        <p class="caveat"><?= e($current['error_message']) ?></p>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="retry">
                            <input type="hidden" name="id" value="<?= (int) $current['id'] ?>">
                            <button class="submit-button" type="submit">Try again</button>
                        </form>
                    <?php else: ?>
                        <p class="verdict uncertain">Analysis pending</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
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
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="upload">
            <label class="dropzone" for="image">
                <span class="upload-icon" aria-hidden="true">↑</span>
                <strong id="file-label">Choose an image to analyse</strong>
                <span id="file-help">JPEG, PNG, WebP or GIF</span>
                <input type="file" id="image" name="image" data-max-bytes="10485760"
                       accept="image/jpeg,image/png,image/webp,image/gif" required>
            </label>

            <div id="preview-container" hidden>
                <img id="image-preview" alt="Preview of the selected image">
            </div>

            <button class="submit-button" type="submit">Analyse image <span aria-hidden="true">→</span></button>
        </form>

        <p class="privacy-note">
            Images are stored on this server so you can revisit results from this browser. You can delete
            any upload below. Nothing is sent to third parties.
        </p>
    </section>

    <section class="history-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">THIS BROWSER</p>
                <h2>Recent uploads</h2>
            </div>
            <span class="count"><?= count($history) ?> records</span>
        </div>

        <?php if (!$history): ?>
            <div class="empty-state">
                <p>No images uploaded yet.</p>
                <span>Your uploads from this browser will appear here.</span>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Image</th><th>Result</th><th>AI chance</th><th>Uploaded</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $item): ?>
                        <?php [$rowLabel, $rowTone] = verdictLabel($verdictLabels, $item['verdict']); ?>
                        <tr>
                            <td class="thumb-cell">
                                <img class="thumb" src="/image.php?id=<?= (int) $item['id'] ?>" alt="" loading="lazy">
                                <a href="/?id=<?= (int) $item['id'] ?>"><?= e($item['original_filename']) ?></a>
                            </td>
                            <td>
                                <?php if ($item['status'] === 'DONE'): ?>
                                    <span class="pill <?= e($rowTone) ?>"><?= e($rowLabel) ?></span>
                                <?php elseif ($item['status'] === 'FAILED'): ?>
                                    <span class="pill uncertain">Failed</span>
                                <?php else: ?>
                                    <span class="pending-label">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['status'] === 'DONE' ? percent($item['ai_probability']) . '%' : '—' ?></td>
                            <td><?= e($item['created_at']) ?></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <button class="link-button" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>ImageCheck · Local image analysis prototype</footer>
</body>
</html>

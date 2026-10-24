<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Support.php';
require_once __DIR__ . '/UploadValidator.php';
require_once __DIR__ . '/InferenceClient.php';
require_once __DIR__ . '/AnalysisRepository.php';
require_once __DIR__ . '/AnalysisService.php';

const UPLOAD_DIR = '/var/www/uploads'; // outside the web root, never served directly

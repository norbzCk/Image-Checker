<?php
declare(strict_types=1);

/** Validates a PHP $_FILES entry. Never trusts the client-supplied name, type or extension. */
final class UploadValidator
{
    private const TYPES = [
        'image/jpeg' => ['ext' => 'jpg',  'imagetype' => IMAGETYPE_JPEG],
        'image/png'  => ['ext' => 'png',  'imagetype' => IMAGETYPE_PNG],
        'image/webp' => ['ext' => 'webp', 'imagetype' => IMAGETYPE_WEBP],
        'image/gif'  => ['ext' => 'gif',  'imagetype' => IMAGETYPE_GIF],
    ];

    public function __construct(
        private int $maxBytes = 10 * 1024 * 1024,
        private int $maxPixels = 40_000_000,
    ) {
    }

    /** @return array{tmp:string,mime:string,ext:string,width:int,height:int,size:int,sha256:string,original:string} */
    public function validate(mixed $file): array
    {
        if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'])) {
            throw new UserError('Please select an image to upload.');
        }

        $this->checkUploadError((int) $file['error']);

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new UserError('The upload could not be processed.');
        }

        $size = (int) filesize($tmp);
        if ($size <= 0 || $size > $this->maxBytes) {
            throw new UserError('The image must be smaller than ' . intdiv($this->maxBytes, 1024 * 1024) . ' MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset(self::TYPES[$mime])) {
            throw new UserError('Use a JPEG, PNG, WebP, or GIF image.');
        }

        $info = @getimagesize($tmp);
        if ($info === false || $info[2] !== self::TYPES[$mime]['imagetype']) {
            throw new UserError('The uploaded file is not a valid image.');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > $this->maxPixels) {
            throw new UserError('The image dimensions are too large.');
        }

        $original = mb_substr(basename((string) $file['name']), 0, 255);
        $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?: 'image';

        return [
            'tmp'      => $tmp,
            'mime'     => $mime,
            'ext'      => self::TYPES[$mime]['ext'],
            'width'    => (int) $width,
            'height'   => (int) $height,
            'size'     => $size,
            'sha256'   => hash_file('sha256', $tmp),
            'original' => $original,
        ];
    }

    private function checkUploadError(int $code): void
    {
        match ($code) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_NO_FILE => throw new UserError('Please select an image to upload.'),
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new UserError(
                'The image must be smaller than ' . intdiv($this->maxBytes, 1024 * 1024) . ' MB.'
            ),
            UPLOAD_ERR_PARTIAL => throw new UserError('The upload was interrupted. Please try again.'),
            default => throw new RuntimeException('Upload failed with PHP error code ' . $code),
        };
    }
}

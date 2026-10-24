CREATE TABLE IF NOT EXISTS image_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    detection_status VARCHAR(40) NOT NULL DEFAULT 'PENDING_MODEL',
    detection_label VARCHAR(100) NOT NULL DEFAULT 'Not yet analyzed',
    confidence DECIMAL(6,5) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- Applied automatically by the MySQL image on first start (empty data volume only).
CREATE TABLE IF NOT EXISTS image_analyses (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_hash       CHAR(64)  NOT NULL,            -- sha256 of the visitor's cookie token
    client_hash      CHAR(64)  NOT NULL,            -- HMAC of the visitor's IP (rate limiting only)
    original_filename VARCHAR(255) NOT NULL,
    stored_filename  VARCHAR(255) NOT NULL,
    mime_type        VARCHAR(100) NOT NULL,
    file_size        BIGINT UNSIGNED NOT NULL,
    width            INT UNSIGNED NOT NULL,
    height           INT UNSIGNED NOT NULL,
    sha256           CHAR(64)  NOT NULL,
    status           ENUM('PENDING','DONE','FAILED') NOT NULL DEFAULT 'PENDING',
    verdict          ENUM('LIKELY_AI','LIKELY_REAL','UNCERTAIN','AI_DECLARED') NULL,
    ai_probability   DECIMAL(5,4) NULL,
    model_name       VARCHAR(150) NULL,
    c2pa_present     TINYINT(1) NULL,
    c2pa_ai_declared TINYINT(1) NULL,
    signals_json     JSON NULL,
    latency_ms       INT UNSIGNED NULL,
    error_message    VARCHAR(500) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    analyzed_at      TIMESTAMP NULL,
    INDEX idx_owner_created (owner_hash, created_at),
    INDEX idx_client_created (client_hash, created_at),
    INDEX idx_sha256 (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

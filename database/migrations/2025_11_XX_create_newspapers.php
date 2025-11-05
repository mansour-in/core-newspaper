<?php

declare(strict_types=1);

return <<<SQL
CREATE TABLE IF NOT EXISTS newspapers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('date','sequence','monthly') NOT NULL,
    base_url VARCHAR(255) NULL,
    pattern VARCHAR(255) NULL,
    local_latest_id BIGINT NULL,
    provider_latest_id BIGINT NULL,
    seed_date_ksa DATE NULL,
    cutover_hour TINYINT NOT NULL DEFAULT 8,
    last_increment_ksa DATE NULL,
    last_redirect_url TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL;

<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../../config/conn.php';
$conn=(new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
if (!$conn->query("SHOW COLUMNS FROM site_settings LIKE 'minimum_cancellation_notice_days'")->fetch()) {
    $conn->exec('ALTER TABLE site_settings ADD minimum_cancellation_notice_days TINYINT UNSIGNED NOT NULL DEFAULT 2');
}
echo "Cancellation notice setting ready.\n";

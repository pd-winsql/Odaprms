<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../../config/conn.php';
$conn = (new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
$conn->exec(file_get_contents(__DIR__ . '/20261003_add_profile_print_requests.sql'));
echo "Profile print request table is ready.\n";

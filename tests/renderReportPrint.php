<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION = ['user_id' => 7, 'user_role' => 'Admin', 'display_name' => 'Administrator'];
$_GET = ['report_type' => $argv[1] ?? 'appointments', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31'];
require __DIR__ . '/../apps/views/admin/partials/reports-content.php';
session_destroy();

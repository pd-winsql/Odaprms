<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../../config/conn.php';
$conn = (new Database())->connect();
if (!$conn) throw new RuntimeException('Database unavailable.');
foreach (['appointments' => 'Treatment Postponed', 'appointment_deposits' => 'Retained for Rebooking'] as $table => $value) {
    $column = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if (str_starts_with($column['Type'], 'enum(') && !str_contains($column['Type'], "'$value'")) {
        $type = substr($column['Type'], 0, -1) . ", '$value')";
        $default = $conn->quote($column['Default']);
        $conn->exec("ALTER TABLE `$table` MODIFY status $type NOT NULL DEFAULT $default");
    }
}
foreach (['postponed_at' => 'DATETIME NULL', 'postponement_reason' => 'VARCHAR(255) NULL'] as $column => $type) {
    if (!$conn->query("SHOW COLUMNS FROM appointments LIKE '$column'")->fetch()) {
        $conn->exec("ALTER TABLE appointments ADD `$column` $type");
    }
}
echo "Treatment postponement schema is ready.\n";

<?php
// Backward-compatible read-only entry point for the guarded migration.
$argv = [$argv[0], '--check'];
require __DIR__ . '/20261003_remove_patient_email.php';

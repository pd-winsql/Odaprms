<?php
// Source-level regression check: no database, SMTP, or real secrets required.
$source = file_get_contents(__DIR__ . '/../apps/controllers/passwordResetController.php');
if ($source === false) {
    throw new RuntimeException('Unable to read the password reset controller.');
}

// This endpoint processes passwords, OTPs, and reset tokens. Request/debug
// logging must not be reintroduced; responses are not log statements.
foreach (['error_log', 'print_r', 'var_dump'] as $function) {
    if (preg_match('/\b' . $function . '\s*\(/i', $source)) {
        throw new RuntimeException('Password reset must not log or dump sensitive request data.');
    }
}
echo "PASS: Password reset does not log or dump passwords, OTPs, or tokens.\n";

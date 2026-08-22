<?php
// apps/helpers/csrf.php
// Simple CSRF helper functions. Keep these intentionally small and easy to read.

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Ensure a session-scoped CSRF token exists and return it.
 * Uses a cryptographically secure generator.
 */
function get_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // Create a token once per session when first requested
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf_token'];
}

/**
 * Validate a provided CSRF token.
 * Priority of sources checked (in order):
 *  1. Explicit provided argument
 *  2. X-CSRF-Token HTTP header (useful for AJAX)
 *  3. POST body field csrf_token (for traditional form submissions)
 *
 * Returns true when the token is present and matches the session token.
 */
function validate_csrf(?string $provided = null): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    if ($expected === '') return false; // no token in session -> invalid

    // If caller supplied a token explicitly, use it.
    if ($provided !== null) {
        $token = (string) $provided;
    } else {
        // Prefer header for AJAX clients
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $token = $header !== '' ? $header : (string) ($_POST['csrf_token'] ?? '');
    }

    if ($token === '') return false;

    // Use hash_equals to avoid timing attacks
    return hash_equals($expected, $token);
}

<?php

$root = dirname(__DIR__);
$register = file_get_contents($root . '/apps/views/register.php');
$landing = file_get_contents($root . '/index.php');
$modal = file_get_contents($root . '/apps/views/system-terms.php');
$page = file_get_contents($root . '/apps/views/terms.php');

$checks = [
    'registration has a locked agreement checkbox' => str_contains($register, 'id="termsAccepted" disabled'),
    'registration opens the terms modal' => str_contains($register, 'data-bs-target="#systemTermsModal"'),
    'agreement is gated by the modal scroll position' => str_contains($register, 'hasReachedTermsEnd()'),
    'registration blocks progression without agreement' => str_contains($register, 'if (!termsAccepted.checked)'),
    'modal agreement starts disabled' => str_contains($modal, 'id="systemTermsAgreeButton" disabled'),
    'modal and public page share one terms-content partial' => str_contains($modal, "require __DIR__ . '/system-terms-content.php'")
        && str_contains($page, "require __DIR__ . '/system-terms-content.php'"),
    'landing page links to the public terms page' => str_contains($landing, 'href="apps/views/terms.php"'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "Terms consent checks failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "PASS: Public terms links and registration consent gate are wired correctly.\n";

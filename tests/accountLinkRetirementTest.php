<?php

$root = dirname(__DIR__);
$applicationFiles = [
    $root . '/apps/controllers/patientController.php',
    $root . '/apps/controllers/userController.php',
    $root . '/apps/models/patientModel.php',
    $root . '/apps/views/admin/partials/patient-content.php',
];

$retiredTokens = [
    'authorizeAccountLink',
    'getActiveLinkAuthorization',
    'link_authorization_id',
    'link_patient_id',
    'data-authorize-link',
    'patient_account_link_authorizations',
];

foreach ($applicationFiles as $file) {
    $contents = file_get_contents($file);
    foreach ($retiredTokens as $token) {
        if (str_contains($contents, $token)) {
            fwrite(STDERR, "Retired account-link token {$token} remains in {$file}.\n");
            exit(1);
        }
    }
}

$schema = file_get_contents($root . '/db-oaprms-system.sql');
if (str_contains($schema, 'patient_account_link_authorizations')) {
    fwrite(STDERR, "The retired account-link table remains in the canonical schema export.\n");
    exit(1);
}

$registration = file_get_contents($root . '/apps/controllers/userController.php');
if (!str_contains($registration, 'findExactIdentity')
    || !str_contains($registration, 'findPossibleIdentityMatches')
    || !str_contains($registration, 'flagPossibleDuplicates')) {
    fwrite(STDERR, "Duplicate-registration safeguards must remain after account-link retirement.\n");
    exit(1);
}

echo "Account-link retirement checks passed.\n";

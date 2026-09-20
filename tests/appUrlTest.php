<?php

require_once __DIR__ . '/../apps/helpers/appUrl.php';

$cases = [
    ['/Capstone System/apps/views/login.php', '', '/Capstone System'],
    ['/clinic-system/apps/views/patient/dashboard.php', '', '/clinic-system'],
    ['/apps/views/admin/dashboard.php', '', ''],
    ['/clinic-system/index.php', '', '/clinic-system'],
    ['/ignored/apps/views/login.php', '/custom-clinic', '/custom-clinic'],
    ['/ignored/apps/views/login.php', 'https://clinic.example.test/portal', 'https://clinic.example.test/portal'],
];

foreach ($cases as [$scriptName, $configuredBase, $expected]) {
    $actual = vdResolveAppBaseUrl($scriptName, $configuredBase);
    if ($actual !== $expected) {
        fwrite(STDERR, "Expected {$expected}, received {$actual}.\n");
        exit(1);
    }
}

echo "Application URL path tests passed.\n";


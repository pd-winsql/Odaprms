<?php

session_save_path(sys_get_temp_dir());
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require __DIR__ . '/../index.php';
$html = ob_get_clean();

$expectations = [
    'the redesigned landing stylesheet is isolated and loaded' => str_contains($html, 'public/css/landing.css') && !str_contains($html, 'public/css/index.css'),
    'the local Bootstrap bundle is used for core navigation' => str_contains($html, 'public/css/bootstrap.min.css') && str_contains($html, 'public/js/bootstrap.bundle.min.js'),
    'the system descriptor renders in the hero' => str_contains($html, 'Online Appointment with Records Management System'),
    'the managed hero image renders outside the copy panel' => str_contains($html, 'class="vd-landing-hero-media"') && str_contains($html, 'landing_hero_default.jpg'),
    'the appointment process explains the records workflow' => str_contains($html, 'From request to dental chair.') && str_contains($html, 'patient records together'),
    'the patient-oriented services heading renders' => str_contains($html, 'Care for every stage of your smile.'),
    'the service explorer renders' => str_contains($html, 'data-service-explorer'),
    'desktop category tabs render' => str_contains($html, 'role="tablist"') && str_contains($html, 'role="tab"'),
    'mobile category accordions render' => str_contains($html, 'data-service-panel') && str_contains($html, 'vd-service-category-summary'),
    'service detail disclosures render' => str_contains($html, 'class="vd-service-item"') && str_contains($html, 'View treatment details'),
    'the explorer enhancement script loads' => str_contains($html, 'public/js/index-services.js'),
    'the obsolete service grid is absent' => !str_contains($html, 'vd-service-grid'),
];

foreach ($expectations as $message => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$message}.\n");
        exit(1);
    }
    echo "PASS: {$message}.\n";
}

echo "Landing services test completed.\n";

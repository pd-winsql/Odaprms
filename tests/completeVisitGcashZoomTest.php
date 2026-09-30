<?php

$root = dirname(__DIR__);
$view = file_get_contents($root . '/apps/views/admin/partials/complete-visit-content.php');
$css = file_get_contents($root . '/public/css/complete-visit.css');

$viewRequirements = [
    'data-gcash-zoom-out',
    'data-gcash-zoom-in',
    'data-gcash-zoom-reset',
    'data-gcash-zoom-level',
    'data-gcash-zoom-stage',
    'data-gcash-zoom-image',
    'minimumZoom = 0.5',
    'maximumZoom = 3',
    "event.key === '+'",
    "event.key === '-'",
    "event.key === '0'",
    "shown.bs.modal",
    "hidden.bs.modal",
];

foreach ($viewRequirements as $requirement) {
    if (!str_contains($view, $requirement)) {
        fwrite(STDERR, "Missing GCash zoom behavior: {$requirement}\n");
        exit(1);
    }
}

$cssRequirements = [
    '.vd-complete-visit-gcash-tools',
    '.vd-complete-visit-gcash-stage',
    'overflow: auto',
    'touch-action: pan-x pan-y',
    ':focus-visible',
];

foreach ($cssRequirements as $requirement) {
    if (!str_contains($css, $requirement)) {
        fwrite(STDERR, "Missing GCash zoom styling: {$requirement}\n");
        exit(1);
    }
}

echo "Complete-visit GCash zoom checks passed.\n";

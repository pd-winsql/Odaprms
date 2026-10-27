<?php $safe=static fn($value)=>htmlspecialchars($value,ENT_QUOTES,'UTF-8'); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment receipt</title><link rel="stylesheet" href="<?= $safe(vdAppUrl('public/css/payment-receipt-preview.css')) ?>"></head>
<body class="vd-receipt-preview"><header><h1>Payment receipt <small><?= $safe($receipt['number']) ?></small></h1>
<nav aria-label="Receipt controls"><button id="receiptZoomOut" aria-label="Zoom out">−</button><output id="receiptZoomLevel" aria-live="polite">100%</output><button id="receiptZoomIn" aria-label="Zoom in">+</button><button id="receiptZoomReset">Reset</button><a href="<?= $safe($base.'&download=1') ?>">Download PNG</a></nav></header>
<main><img id="paymentReceiptImage" src="<?= $safe($base) ?>" alt="Itemized payment receipt for your completed visit"></main>
<script src="<?= $safe(vdAppUrl('public/js/payment-receipt-preview.js')) ?>"></script></body></html>

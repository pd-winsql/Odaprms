<?php
require_once '../helpers/siteBranding.php';
$branding = vdLoadSiteBranding();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms and Conditions | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&amp;family=Jost:wght@300;400;500&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
  <link rel="stylesheet" href="../../public/css/styles.css?v=<?= filemtime(__DIR__ . '/../../public/css/styles.css') ?>">
  <link rel="stylesheet" href="../../public/css/terms.css?v=<?= filemtime(__DIR__ . '/../../public/css/terms.css') ?>">
</head>
<body class="vd-terms-page-body">
  <header class="vd-terms-page-header">
    <a class="vd-terms-wordmark" href="../../index.php" aria-label="Return to Dr. Aprille Ventura Clinica Dental home page">
      <?= vdRenderSiteBranding($branding, '../../public/assets') ?>
    </a>
  </header>

  <main class="vd-terms-page-main">
    <div class="vd-terms-page-heading">
      <div class="vd-terms-eyebrow">Online Platform</div>
      <h1 class="vd-terms-title">System Terms and Conditions</h1>
      <p>Please review the terms governing use of the clinic’s online appointment and patient records system.</p>
    </div>
    <article class="vd-terms-body">
      <?php require __DIR__ . '/system-terms-content.php'; ?>
    </article>
    <a class="vd-terms-back-link" href="../../index.php">← Back to home</a>
  </main>
</body>
</html>

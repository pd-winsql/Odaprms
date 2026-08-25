<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'config/conn.php';
require_once 'apps/models/clinicModel.php';
require_once 'apps/models/serviceModel.php';
require_once 'apps/models/siteSettingsModel.php';
require_once 'apps/helpers/siteBranding.php';
require_once 'apps/helpers/csrf.php';

$db = new Database();
$conn = $db->connect();
$clinicModel = new Clinic($conn);
$clinics = $clinicModel->getAllClinics();

$settingsModel = new SiteSettingsModel($conn);
$settings = $settingsModel->getSettings();

function sv($settings, $key, $fallback = '')
{
  return htmlspecialchars($settings[$key] ?? $fallback);
}

function contactPhones($settings): array
{
  $raw = trim((string)($settings['contact_phone'] ?? ''));
  if ($raw === '') {
    return ['0912-345-6789'];
  }

  $phones = preg_split('/[\r\n,;]+/', $raw) ?: [];
  $phones = array_values(array_filter(array_map('trim', $phones), fn($value) => $value !== ''));
  return $phones ?: ['0912-345-6789'];
}

$serviceModel = new ServiceModel($conn);
$allCategories = $serviceModel->getAllCategories();
$allServices   = $serviceModel->getAllServices();

// Build category_id => [service_id, ...] from services.category_id
$categoryServiceIds = [];
foreach ($allServices as $service) {
  $serviceId = (int)($service['service_id'] ?? 0);
  $categoryId = (int)($service['category_id'] ?? 0);

  if ($serviceId <= 0 || $categoryId <= 0) {
    continue;
  }

  $categoryServiceIds[$categoryId][] = $serviceId;
}
$servicesById = array_column($allServices, null, 'service_id');

// Reshape into the same [title, description, services[]] structure the
// template below already expects, so the markup itself didn't need to change.
// Only active services are shown on the public landing page.
$serviceCategories = [];
$activeServiceCount = 0;
foreach ($allCategories as $cat) {
  $serviceIds = $categoryServiceIds[$cat['category_id']] ?? [];
  $categoryServices = [];

  foreach ($serviceIds as $sid) {
    if (!isset($servicesById[$sid]) || (int)$servicesById[$sid]['is_active'] !== 1) continue;
    $categoryServices[] = [
      'name' => $servicesById[$sid]['service_name'],
      'icon' => $servicesById[$sid]['service_icon'],
      'desc' => $servicesById[$sid]['service_description'],
    ];
    $activeServiceCount++;
  }

  if (empty($categoryServices)) continue; // skip empty categories on the public page

  $serviceCategories[] = [
    'title' => $cat['category_name'],
    'description' => $cat['category_description'],
    'services' => $categoryServices,
  ];
}

$isLoggedIn = isset($_SESSION['user_id']);

$dashboardUrl = match ($_SESSION['user_role'] ?? '') {
  'Admin'            => 'apps/views/admin/dashboard.php',
  'Dental Assistant' => 'apps/views/dental_asst/dashboard.php',
  'Patient'          => 'apps/views/patient/dashboard.php',
  default            => 'index.php',
};
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="public/css/styles.css">
  <link rel="stylesheet" href="public/css/index.css?v=20260821-1">
  <link rel="stylesheet" href="public/css/loading.css">
  <script src="public/js/loading.js" defer></script>
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg vd-navbar sticky-top">
    <div class="container-fluid px-4 px-lg-5">
      <a class="navbar-brand vd-navbar-brand-wrap" href="#hero-section">
        <?= vdRenderSiteBranding($settings, 'public/assets', 'navbar') ?>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-2">
          <li class="nav-item"><a href="#hero-section" class="nav-link vd-nav-link">Home</a></li>
          <li class="nav-item"><a href="#services" class="nav-link vd-nav-link">Services</a></li>
          <li class="nav-item"><a href="#about" class="nav-link vd-nav-link">About Us</a></li>
          <li class="nav-item"><a href="#contact" class="nav-link vd-nav-link">Contact</a></li>
        </ul>
        <div class="d-flex gap-2">
          <?php if ($isLoggedIn): ?>
            <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn vd-btn-gold">Go to Dashboard</a>
            <a href="apps/controllers/userController.php?action=logout" class="btn vd-btn-outline">Logout</a>
          <?php else: ?>
            <a href="apps/views/register.php" class="btn vd-btn-gold">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section id="hero-section" class="vd-hero d-flex align-items-center">
    <div class="container">
      <div class="row align-items-center gy-5 gx-0 gx-md-5">
        <div class="d-none d-lg-block col-lg-6"></div>
        <div class="col-12 col-lg-6">
          <div class="vd-hero-card ms-lg-auto">
            <div class="vd-hero-system-tag"><?= sv($settings, 'hero_system_tag', 'Online Dental Appointment & Patient Records Management System') ?></div>
            <div class="vd-hero-eyebrow"><?= sv($settings, 'hero_eyebrow', 'Two Clinics in Cagayan · Alcala & Tuguegarao') ?></div>
            <h1 class="vd-hero-title"><?= sv($settings, 'hero_title', 'Dental care for Alcala and Tuguegarao families.') ?></h1>
            <p class="vd-hero-sub"><?= sv($settings, 'hero_subtext', 'From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.') ?></p>
            <div class="d-flex flex-wrap gap-3">
              <a href="<?= $isLoggedIn && ($_SESSION['user_role'] ?? '') === 'Patient' ? 'apps/views/patient/dashboard.php#booking-content.php' : 'apps/views/login.php?next=booking' ?>" class="btn vd-btn-gold px-4 py-2">Book an Appointment</a>
              <a href="#services" class="btn vd-btn-outline px-4 py-2">View Services</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Signature smile-curve divider into the next section -->
    <div class="vd-arc-divider">
      <svg viewBox="0 0 1440 100" preserveAspectRatio="none">
        <path class="vd-arc-fill-white" d="M0,100 Q720,-100 1440,100 Z"></path>
      </svg>
    </div>
  </section>

  <!-- SERVICES -->
  <section id="services" class="py-5 vd-services-section">
    <div class="container">
      <div class="text-center mb-5">
        <div class="vd-eyebrow">What We Offer</div>
        <h2 class="vd-section-heading mb-2">Our Services</h2>
        <p class="vd-section-intro">Care organized by category, from everyday prevention to more involved restorative, surgical, and cosmetic treatment.</p>
      </div>

      <?php foreach ($serviceCategories as $category): ?>
        <div class="vd-service-category">
          <div class="vd-service-category-header">
            <h3 class="vd-service-category-title"><?= htmlspecialchars($category['title']) ?></h3>
            <p class="vd-service-category-desc"><?= htmlspecialchars($category['description']) ?></p>
          </div>
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
            <?php foreach ($category['services'] as $service): ?>
              <div class="col">
                <div class="vd-service-item h-100">
                  <div class="vd-service-icon"><i class="<?= htmlspecialchars($service['icon']) ?>"></i></div>
                  <div>
                    <div class="vd-service-name"><?= htmlspecialchars($service['name']) ?></div>
                    <div class="vd-service-desc"><?= htmlspecialchars($service['desc']) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ABOUT -->
  <section id="about" class="py-5 border-top">
    <div class="container">
      <div class="text-center mb-4">
        <div class="vd-eyebrow">Who We Are</div>
        <h2 class="vd-section-heading mb-2">About Us</h2>
        <p class="vd-section-intro"><?= sv($settings, 'about_intro') ?></p>
      </div>

      <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <div class="col">
          <div class="vd-pillar text-center h-100">
            <div class="vd-pillar-icon"><i class="fa-solid fa-heart"></i></div>
            <div class="vd-pillar-title"><?= sv($settings, 'pillar1_title', 'Patient-Centered Care') ?></div>
            <p class="vd-pillar-desc"><?= sv($settings, 'pillar1_desc') ?></p>
          </div>
        </div>
        <div class="col">
          <div class="vd-pillar text-center h-100">
            <div class="vd-pillar-icon"><i class="fa-solid fa-award"></i></div>
            <div class="vd-pillar-title"><?= sv($settings, 'pillar2_title', 'Experienced Team') ?></div>
            <p class="vd-pillar-desc"><?= sv($settings, 'pillar2_desc') ?></p>
          </div>
        </div>
        <div class="col">
          <div class="vd-pillar text-center h-100">
            <div class="vd-pillar-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div class="vd-pillar-title"><?= sv($settings, 'pillar3_title', 'Two Convenient Branches') ?></div>
            <p class="vd-pillar-desc"><?= sv($settings, 'pillar3_desc') ?></p>
          </div>
        </div>
      </div>

      <div class="text-center mb-4">
        <div class="vd-eyebrow">Visit Us</div>
        <h2 class="vd-section-heading mb-2">Our Clinics</h2>
      </div>
      <div class="row justify-content-center g-4">
        <?php foreach ($clinics as $clinic): ?>
          <div class="col-12 col-sm-6 col-md-4">
            <div class="card vd-clinic-card-index h-100 text-center border">
              <div class="card-body">
                <div class="vd-clinic-icon-badge mx-auto">
                  <i class="fa-solid fa-location-dot"></i>
                </div>
                <h5 class="card-title"><?= htmlspecialchars($clinic['clinic_name']) ?></h5>
                <p class="card-text small text-muted"><?= htmlspecialchars($clinic['clinic_address']) ?></p>
                <?php if (!empty($clinic['embed_url'])): ?>
                  <div class="ratio ratio-4x3 mt-3">
                    <iframe
                      src="<?= htmlspecialchars($clinic['embed_url']) ?>"
                      style="border:0;"
                      allowfullscreen=""
                      loading="lazy"
                      referrerpolicy="strict-origin-when-cross-origin"></iframe>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CONTACT -->
  <section id="contact" class="py-5 vd-contact-bg border-top">
    <div class="container">
      <div class="row justify-content-center gy-5 gx-0 gx-md-5 align-items-center">
        <div class="col-12 col-md-5">
          <div class="vd-eyebrow mb-2">Get In Touch</div>
          <h2 class="vd-contact-heading mb-3">We'd Love to Hear From You</h2>
          <p class="text-muted mb-1">Address: <?= sv($settings, 'contact_address', 'Alcala & Tuguegarao, Cagayan') ?></p>
          <?php foreach (contactPhones($settings) as $phone): ?>
            <p class="text-muted mb-1">Phone: <?= htmlspecialchars($phone) ?></p>
          <?php endforeach; ?>
          <p class="text-muted">Email: <a href="mailto:<?= sv($settings, 'contact_email', 'info@draprilleventura.com') ?>" class="vd-link"><?= sv($settings, 'contact_email', 'info@draprilleventura.com') ?></a></p>
        </div>
        <div class="col-12 col-md-5">
          <div class="card vd-form-card p-4 border">
            <form action="mailto:<?= sv($settings, 'contact_email', 'info@draprilleventura.com') ?>" method="POST" enctype="text/plain">
              <div class="mb-3">
                <label for="name" class="form-label vd-label">Name</label>
                <input type="text" id="name" name="name" class="form-control vd-input" required>
              </div>
              <div class="mb-3">
                <label for="index-email" class="form-label vd-label">Your Email</label>
                <input type="email" id="index-email" name="email" class="form-control vd-input" required>
              </div>
              <div class="mb-3">
                <label for="message" class="form-label vd-label">Message</label>
                <textarea id="message" name="message" rows="5" class="form-control vd-input" required></textarea>
              </div>
              <button type="submit" class="btn vd-btn-gold w-100">Send Message</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="vd-footer py-3">
    <div class="container text-center">
      <a
        href="#systemTermsModal"
        class="vd-footer-link"
        data-bs-toggle="modal"
        aria-label="Read the system terms and conditions">
        <span>System Terms and Conditions</span>
      </a>
      <p class="mb-0 mt-2 small text-white">
        &copy; <script>
          document.write(new Date().getFullYear())
        </script> Dr. Aprille Ventura Clinica Dental. All rights reserved.
      </p>
    </div>
  </footer>

  <!-- System terms are public. Clinic-specific terms remain unpublished until supplied. -->
  <?php require __DIR__ . '/apps/views/system-terms.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

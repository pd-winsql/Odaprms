<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'config/conn.php';
require_once 'apps/models/clinicModel.php';
require_once 'apps/models/serviceModel.php';
require_once 'apps/models/siteSettingsModel.php';
require_once 'apps/helpers/siteBranding.php';
require_once 'apps/helpers/csrf.php';
require_once 'apps/helpers/serviceImage.php';

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
      'id' => (int)$sid,
      'name' => $servicesById[$sid]['service_name'],
      'image' => vdServiceImageUrl($servicesById[$sid]['service_image'] ?? null),
      'desc' => $servicesById[$sid]['service_description'],
    ];
    $activeServiceCount++;
  }

  if (empty($categoryServices)) continue; // skip empty categories on the public page

  $serviceCategories[] = [
    'id' => (int)$cat['category_id'],
    'title' => $cat['category_name'],
    'description' => $cat['category_description'],
    'image' => array_values(array_filter(array_column($categoryServices, 'image')))[0] ?? '',
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
$bookingUrl = $isLoggedIn && ($_SESSION['user_role'] ?? '') === 'Patient'
  ? 'apps/views/patient/dashboard.php#booking-content.php'
  : 'apps/views/login.php?next=booking';
$heroImage = basename((string)($settings['hero_image'] ?? 'landing_hero_default.jpg'));
if (!preg_match('/^(?:landing_hero_default\.jpg|hero_image_[a-f0-9]{32}\.(?:jpg|png|webp))$/D', $heroImage)) {
  $heroImage = 'landing_hero_default.jpg';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dr. Aprille Ventura Clinica Dental</title>
  <link href="public/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public/css/styles.css?v=<?= filemtime(__DIR__ . '/public/css/styles.css') ?>">
  <link rel="stylesheet" href="public/css/landing.css?v=<?= filemtime(__DIR__ . '/public/css/landing.css') ?>">
  <link rel="stylesheet" href="public/css/loading.css">
  <script src="public/js/loading.js" defer></script>
</head>

<body class="vd-landing-page">

  <a class="vd-landing-skip" href="#main-content">Skip to main content</a>

  <header class="vd-landing-header">
  <nav class="navbar navbar-expand-lg vd-landing-nav" aria-label="Primary navigation">
    <div class="container vd-landing-nav-inner">
      <a class="navbar-brand vd-landing-brand" href="#hero-section" aria-label="Dr. Aprille Ventura Clinica Dental home">
        <?= vdRenderSiteBranding($settings, 'public/assets', 'navbar') ?>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
        aria-controls="navMenu" aria-expanded="false" aria-label="Open navigation menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav ms-auto mb-3 mb-lg-0 vd-landing-links">
          <li class="nav-item"><a href="#services" class="nav-link">Services</a></li>
          <li class="nav-item"><a href="#clinics" class="nav-link">Clinics</a></li>
          <li class="nav-item"><a href="#about" class="nav-link">About</a></li>
          <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>
        </ul>
        <div class="vd-landing-nav-actions">
          <?php if ($isLoggedIn): ?>
            <div class="dropdown vd-landing-account-menu">
              <button class="vd-landing-account-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                <span>My account</span>
              </button>
              <div class="dropdown-menu dropdown-menu-end vd-landing-account-dropdown">
                <span class="vd-landing-account-role"><?= htmlspecialchars((string)($_SESSION['user_role'] ?? 'Account')) ?></span>
                <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="dropdown-item">
                  <span>Open dashboard</span><span aria-hidden="true">&rarr;</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?= htmlspecialchars(vdAppUrl('apps/controllers/userController.php?action=logout'), ENT_QUOTES, 'UTF-8') ?>" class="dropdown-item vd-landing-signout">Sign out</a>
              </div>
            </div>
          <?php else: ?>
            <a href="apps/views/login.php" class="vd-landing-utility-link">Sign in <span aria-hidden="true">&rarr;</span></a>
            <a href="apps/views/register.php" class="vd-landing-account-link">Create account</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
  </header>

  <main id="main-content">
  <section id="hero-section" class="vd-landing-hero" aria-labelledby="landingHeroTitle">
    <figure class="vd-landing-hero-media">
      <img src="public/assets/<?= htmlspecialchars($heroImage) ?>" alt="A young patient receiving attentive dental care" width="2880" height="3600">
    </figure>
    <div class="vd-landing-hero-panel">
      <div class="vd-landing-hero-orbit vd-landing-hero-orbit--one" aria-hidden="true"></div>
      <div class="vd-landing-hero-orbit vd-landing-hero-orbit--two" aria-hidden="true"></div>
      <div class="vd-landing-hero-copy">
        <div class="vd-landing-hero-context">
          <p class="vd-landing-system-tag"><?= sv($settings, 'hero_system_tag', 'Online Appointment with Records Management System') ?></p>
          <p class="vd-landing-kicker"><?= sv($settings, 'hero_eyebrow', 'Two Clinics in Cagayan · Alcala & Tuguegarao') ?></p>
        </div>
        <h1 id="landingHeroTitle"><?= sv($settings, 'hero_title', 'Dental care for Alcala and Tuguegarao families.') ?></h1>
        <p class="vd-landing-hero-summary"><?= sv($settings, 'hero_subtext', 'From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.') ?></p>
        <div class="vd-landing-hero-actions">
          <a href="<?= htmlspecialchars($bookingUrl) ?>" class="vd-landing-button vd-landing-button--dark">Book an appointment</a>
          <a href="#services" class="vd-landing-hero-link">Explore services <span aria-hidden="true">&darr;</span></a>
        </div>
      </div>
    </div>
  </section>

  <section class="vd-landing-process" aria-labelledby="appointmentProcessTitle" data-landing-reveal>
    <div class="container">
      <header class="vd-landing-section-intro">
        <p class="vd-landing-kicker">Designed around your visit</p>
        <h2 id="appointmentProcessTitle">From request to dental chair.</h2>
        <p>A guided online process keeps your appointment details and patient records together.</p>
      </header>
      <ol class="vd-landing-steps">
        <li><span>01</span><div><h3>Choose your care</h3><p>Select a clinic, an available schedule, and the services you may need.</p></div></li>
        <li><span>02</span><div><h3>Confirm your request</h3><p>Submit the required deposit and wait for the clinic’s email confirmation.</p></div></li>
        <li><span>03</span><div><h3>Arrive prepared</h3><p>Your profile, health information, appointment, and visit history stay in one account.</p></div></li>
      </ol>
    </div>
  </section>

  <!-- SERVICES -->
  <section id="services" class="vd-services-section" data-landing-reveal>
    <div class="container">
      <header class="vd-services-heading">
        <div class="vd-eyebrow">What We Offer</div>
        <h2 class="vd-section-heading">Care for every stage of your smile.</h2>
        <p>Browse treatments by type. If you are unsure what you need, choose a consultation and the clinic team will guide you after an examination.</p>
      </header>

      <?php if ($serviceCategories): ?>
        <div class="vd-service-explorer" data-service-explorer>
          <?php if (count($serviceCategories) > 1): ?>
            <nav class="vd-service-category-nav" role="tablist" aria-label="Browse service categories">
              <span class="vd-service-nav-label">Explore care</span>
              <?php foreach ($serviceCategories as $categoryIndex => $category): ?>
                <a
                  href="#service-category-<?= (int)$category['id'] ?>"
                  id="service-tab-<?= (int)$category['id'] ?>"
                  class="vd-service-category-tab<?= $categoryIndex === 0 ? ' is-active' : '' ?>"
                  role="tab"
                  aria-selected="<?= $categoryIndex === 0 ? 'true' : 'false' ?>"
                  aria-controls="service-category-<?= (int)$category['id'] ?>">
                  <span><?= htmlspecialchars($category['title']) ?></span>
                  <span class="vd-service-category-count" aria-label="<?= count($category['services']) ?> services"><?= str_pad((string)count($category['services']), 2, '0', STR_PAD_LEFT) ?></span>
                </a>
              <?php endforeach; ?>
            </nav>
          <?php endif; ?>

          <div class="vd-service-panels">
            <?php foreach ($serviceCategories as $categoryIndex => $category): ?>
              <details
                class="vd-service-category"
                id="service-category-<?= (int)$category['id'] ?>"
                data-service-panel
                data-tab="service-tab-<?= (int)$category['id'] ?>"
                open>
                <summary class="vd-service-category-summary">
                  <span>
                    <span class="vd-service-category-order"><?= str_pad((string)($categoryIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <strong><?= htmlspecialchars($category['title']) ?></strong>
                  </span>
                  <span class="vd-service-summary-meta">
                    <?= count($category['services']) ?> treatment<?= count($category['services']) === 1 ? '' : 's' ?>
                    <span class="vd-service-summary-chevron" aria-hidden="true">⌄</span>
                  </span>
                </summary>

                <div class="vd-service-category-body">
                  <header class="vd-service-category-header">
                    <div class="vd-service-category-media">
                      <?php if ($category['image'] !== ''): ?>
                        <img src="<?= htmlspecialchars($category['image']) ?>" alt="" loading="lazy" width="1200" height="900">
                      <?php else: ?>
                        <span class="vd-service-image-placeholder">
                          <span>Dental care</span>
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="vd-service-category-copy">
                      <span class="vd-service-category-order">Category <?= str_pad((string)($categoryIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                      <h3 class="vd-service-category-title"><?= htmlspecialchars($category['title']) ?></h3>
                      <p class="vd-service-category-desc"><?= htmlspecialchars($category['description']) ?></p>
                      <p class="vd-service-category-note">Treatment recommendations are confirmed after a dental examination.</p>
                    </div>
                  </header>

                  <div class="vd-service-list" aria-label="<?= htmlspecialchars($category['title']) ?> services">
                    <?php foreach ($category['services'] as $serviceIndex => $service): ?>
                      <details class="vd-service-item" id="service-<?= (int)$service['id'] ?>">
                        <summary>
                          <span class="vd-service-number"><?= str_pad((string)($serviceIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                          <span class="vd-service-identity">
                            <strong class="vd-service-name"><?= htmlspecialchars($service['name']) ?></strong>
                            <span>View treatment details</span>
                          </span>
                          <span class="vd-service-item-toggle" aria-hidden="true">+</span>
                        </summary>
                        <div class="vd-service-detail">
                          <p><?= htmlspecialchars($service['desc']) ?></p>
                        </div>
                      </details>
                    <?php endforeach; ?>
                  </div>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <p class="vd-services-empty">Service information is being updated. Please contact the clinic for assistance.</p>
      <?php endif; ?>

      <div class="vd-services-cta">
        <div>
          <h3>Not sure which treatment you need?</h3>
          <p>Book a visit and let the clinic team help you choose the appropriate care.</p>
        </div>
        <a href="<?= htmlspecialchars($bookingUrl) ?>" class="vd-landing-button vd-landing-button--solid">Book a consultation</a>
      </div>
    </div>
  </section>

  <section id="about" class="vd-landing-about" data-landing-reveal>
    <div class="container">
      <div class="vd-landing-about-grid">
        <header class="vd-landing-section-intro">
          <p class="vd-landing-kicker">The clinic behind your care</p>
          <h2>Thoughtful dentistry, clearly explained.</h2>
          <p><?= sv($settings, 'about_intro', 'Patient-centered dental care across Alcala and Tuguegarao, with every step explained clearly.') ?></p>
        </header>
        <ol class="vd-landing-principles">
          <li><span>01</span><div><h3><?= sv($settings, 'pillar1_title', 'Patient-Centered Care') ?></h3><p><?= sv($settings, 'pillar1_desc') ?></p></div></li>
          <li><span>02</span><div><h3><?= sv($settings, 'pillar2_title', 'Experienced Team') ?></h3><p><?= sv($settings, 'pillar2_desc') ?></p></div></li>
          <li><span>03</span><div><h3><?= sv($settings, 'pillar3_title', 'Two Convenient Branches') ?></h3><p><?= sv($settings, 'pillar3_desc') ?></p></div></li>
        </ol>
      </div>
    </div>
  </section>

  <section id="clinics" class="vd-landing-clinics" data-landing-reveal>
    <div class="container">
      <header class="vd-landing-section-intro vd-landing-section-intro--wide">
        <div>
          <p class="vd-landing-kicker">Visit us</p>
          <h2>Care close to home.</h2>
        </div>
        <p>Choose the clinic most convenient for your appointment. Available schedules are shown during booking.</p>
      </header>
      <div class="vd-landing-clinic-list">
        <?php foreach ($clinics as $clinic): ?>
          <article class="vd-landing-clinic">
            <div class="vd-landing-clinic-number"><?= str_pad((string)($clinic['clinic_id'] ?? 0), 2, '0', STR_PAD_LEFT) ?></div>
            <div><h3><?= htmlspecialchars($clinic['clinic_name']) ?></h3><p><?= htmlspecialchars($clinic['clinic_address']) ?></p></div>
            <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($clinic['clinic_address']) ?>" target="_blank" rel="noopener noreferrer">Get directions <span aria-hidden="true">↗</span></a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="contact" class="vd-landing-contact" data-landing-reveal>
    <div class="container">
      <div class="vd-landing-contact-grid">
        <div class="vd-landing-contact-copy">
          <p class="vd-landing-kicker">Contact the clinic</p>
          <h2>Questions before you book?</h2>
          <p>Send a message or contact the clinic directly. For emergencies, seek immediate medical or dental assistance.</p>
          <address>
          <span><?= sv($settings, 'contact_address', 'Alcala & Tuguegarao, Cagayan') ?></span>
          <?php foreach (contactPhones($settings) as $phone): ?>
            <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= htmlspecialchars($phone) ?></a>
          <?php endforeach; ?>
          <a href="mailto:<?= sv($settings, 'contact_email', 'info@draprilleventura.com') ?>"><?= sv($settings, 'contact_email', 'info@draprilleventura.com') ?></a>
          </address>
        </div>
        <div class="vd-landing-contact-action">
          <p>Ready to request a schedule?</p>
          <a href="<?= htmlspecialchars($bookingUrl) ?>" class="vd-landing-button vd-landing-button--dark">Book an appointment</a>
          <?php if (!$isLoggedIn): ?><a href="apps/views/login.php" class="vd-landing-button vd-landing-button--line">Sign in to your account</a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  </main>

  <footer class="vd-landing-footer">
    <div class="container vd-landing-footer-grid">
      <div class="vd-landing-footer-brand"><?= vdRenderSiteBranding($settings, 'public/assets', 'navbar') ?></div>
      <p>Online appointments and patient records for the clinic’s Alcala and Tuguegarao branches.</p>
      <nav aria-label="Footer navigation">
        <a href="#services">Services</a>
        <a href="#clinics">Clinics</a>
        <a href="apps/views/terms.php">System Terms and Conditions</a>
      </nav>
      <small>&copy; <span id="landingCopyrightYear"><?= date('Y') ?></span> Dr. Aprille Ventura Clinica Dental.</small>
    </div>
  </footer>

  <script src="public/js/bootstrap.bundle.min.js"></script>
  <script src="public/js/index-services.js?v=<?= filemtime(__DIR__ . '/public/js/index-services.js') ?>"></script>
  <script src="public/js/index.js?v=<?= filemtime(__DIR__ . '/public/js/index.js') ?>"></script>
</body>

</html>

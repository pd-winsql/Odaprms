<?php
  if (session_status() === PHP_SESSION_NONE) session_start();

  require_once 'config/conn.php';
  require_once 'apps/models/clinicModel.php';

  $db = new Database();
  $conn = $db->connect();
  $clinicModel = new Clinic($conn);
  $clinics = $clinicModel->getAllClinics();

  $isLoggedIn = isset($_SESSION['user_id']);

  $dashboardUrl = match($_SESSION['user_role'] ?? '') {
      'Admin'            => 'apps/views/admin/dashboard.php',
      'Dental Assistant' => 'apps/views/dental_asst/dashboard.php',
      'Patient'          => 'apps/views/patient/dashboard.php',
      default            => 'index.php',
  };

  // Services grouped to match the categories already used in the booking form
  // (Preventive, Restorative, Surgical, Cosmetic). Descriptions are placeholder
  // copy — feel free to refine the wording once you finalize official text.
  $serviceCategories = [
    [
      'title' => 'Preventive & Diagnostic Care',
      'description' => 'Routine visits that catch problems early and keep your smile healthy in between appointments.',
      'services' => [
        ['name' => 'Cleaning (Prophylaxis)', 'icon' => 'fa-solid fa-broom', 'desc' => 'Professional plaque and tartar removal for a fresher, healthier smile.'],
        ['name' => 'Scaling', 'icon' => 'fa-solid fa-teeth', 'desc' => 'Deep cleaning below the gumline to treat and help prevent gum disease.'],
        ['name' => 'Periapical X-ray', 'icon' => 'fa-solid fa-x-ray', 'desc' => 'Detailed imaging of a tooth\'s root and the surrounding bone.'],
      ],
    ],
    [
      'title' => 'Restorative Treatments',
      'description' => 'Repairing damaged, decayed, or missing teeth so you can bite, chew, and smile with confidence.',
      'services' => [
        ['name' => 'Restoration (Fillings)', 'icon' => 'fa-solid fa-tooth', 'desc' => 'Composite or amalgam fillings that repair cavities and minor damage.'],
        ['name' => 'Crown / Jackets', 'icon' => 'fa-solid fa-crown', 'desc' => 'A custom cap that protects and rebuilds a weakened or broken tooth.'],
        ['name' => 'Bridge', 'icon' => 'fa-solid fa-link', 'desc' => 'A fixed replacement that closes the gap left by a missing tooth.'],
        ['name' => 'Root Canal', 'icon' => 'fa-solid fa-syringe', 'desc' => 'Treats infected or damaged tooth pulp to help save the natural tooth.'],
        ['name' => 'Dentures', 'icon' => 'fa-solid fa-teeth', 'desc' => 'Removable replacements for some or all missing teeth.'],
      ],
    ],
    [
      'title' => 'Oral Surgery',
      'description' => 'Extractions and surgical procedures performed with care, plus clear aftercare guidance.',
      'services' => [
        ['name' => 'Extraction', 'icon' => 'fa-solid fa-tooth', 'desc' => 'Safe removal of a damaged, decayed, or problematic tooth.'],
        ['name' => 'Wisdom Tooth Removal', 'icon' => 'fa-solid fa-tooth', 'desc' => 'Removal of impacted or emerging third molars.'],
      ],
    ],
    [
      'title' => 'Cosmetic & Orthodontic',
      'description' => 'Options to align, brighten, and refine the appearance of your smile.',
      'services' => [
        ['name' => 'Braces', 'icon' => 'fa-solid fa-teeth-open', 'desc' => 'Gradually aligns crowded, gapped, or misaligned teeth over time.'],
        ['name' => 'Whitening', 'icon' => 'fa-solid fa-star', 'desc' => 'A professional treatment to brighten stained or discolored teeth.'],
        ['name' => 'Veneer', 'icon' => 'fa-solid fa-gem', 'desc' => 'Thin custom shells that reshape and brighten the front of a tooth.'],
      ],
    ],
  ];
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
  <link rel="stylesheet" href="public/css/index.css">
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg vd-navbar sticky-top">
    <div class="container-fluid px-4 px-lg-5">
      <a class="navbar-brand vd-navbar-brand-wrap" href="#hero-section">
        <div class="vd-logo-name">Dr. Aprille</div>
        <div class="vd-logo-ventura">VEN<span class="vd-cross">✚</span>URA</div>
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
      <div class="row align-items-center g-5">
        <div class="col-12 col-lg-6">
          <div class="vd-hero-card">
            <div class="vd-hero-system-tag">Online Dental Appointment &amp; Patient Records Management System</div>
            <div class="vd-hero-eyebrow">Two Clinics in Cagayan · Alcala &amp; Tuguegarao</div>
            <h1 class="vd-hero-title">Dental care for Alcala and Tuguegarao families.</h1>
            <p class="vd-hero-sub">From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.</p>
            <div class="d-flex flex-wrap gap-3">
              <a href="apps/views/ventura_booking_form.php" class="btn vd-btn-gold px-4 py-2">Book an Appointment</a>
              <a href="#services" class="btn vd-btn-outline px-4 py-2">View Services</a>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="vd-hero-feature-grid">
            <div class="vd-feature-tile">
              <div class="vd-feature-icon"><i class="fa-solid fa-tooth"></i></div>
              <div class="vd-feature-label">13 Dental Services</div>
            </div>
            <div class="vd-feature-tile">
              <div class="vd-feature-icon"><i class="fa-solid fa-location-dot"></i></div>
              <div class="vd-feature-label">2 Branches in Cagayan</div>
            </div>
            <div class="vd-feature-tile">
              <div class="vd-feature-icon"><i class="fa-solid fa-calendar-check"></i></div>
              <div class="vd-feature-label">Easy Online Booking</div>
            </div>
            <div class="vd-feature-tile">
              <div class="vd-feature-icon"><i class="fa-solid fa-hand-holding-medical"></i></div>
              <div class="vd-feature-label">Patient-Centered Care</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Signature smile-curve divider into the next section -->
    <div class="vd-arc-divider">
      <svg viewBox="0 0 1440 100" preserveAspectRatio="none">
        <path class="vd-arc-fill-white" d="M0,0 C360,100 1080,100 1440,0 L1440,100 L0,100 Z"></path>
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
        <p class="vd-section-intro">Dr. Aprille Ventura Clinica Dental provides patient-centered dental care across our Alcala and Tuguegarao branches — from routine checkups to more involved restorative and cosmetic treatment. Our team takes the time to walk you through every step, so you always know what to expect before, during, and after your visit.</p>
      </div>

      <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <div class="col">
          <div class="vd-pillar text-center h-100">
            <div class="vd-pillar-icon"><i class="fa-solid fa-heart"></i></div>
            <div class="vd-pillar-title">Patient-Centered Care</div>
            <p class="vd-pillar-desc">Every visit is explained clearly, so you always know what to expect.</p>
          </div>
        </div>
        <div class="col">
          <div class="vd-pillar text-center h-100">
            <div class="vd-pillar-icon"><i class="fa-solid fa-award"></i></div>
            <div class="vd-pillar-title">Experienced Team</div>
            <p class="vd-pillar-desc">Dental professionals handling everything from routine care to advanced treatment.</p>
          </div>
        </div>
        <div class="col">
          <div class="vd-pillar text-center h-100">
            <div class="vd-pillar-icon"><i class="fa-solid fa-location-dot"></i></div>
            <div class="vd-pillar-title">Two Convenient Branches</div>
            <p class="vd-pillar-desc">Serving patients in both Alcala and Tuguegarao, Cagayan.</p>
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
              <p class="card-text small text-muted">Phone: <?= htmlspecialchars($clinic['clinic_contact']) ?></p>
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
      <div class="row justify-content-center g-5 align-items-center">
        <div class="col-12 col-md-5">
          <div class="vd-eyebrow mb-2">Get In Touch</div>
          <h2 class="vd-contact-heading mb-3">We'd Love to Hear From You</h2>
          <p class="text-muted mb-1">Address: Alcala &amp; Tuguegarao, Cagayan</p>
          <p class="text-muted mb-1">Phone: 0912-345-6789</p>
          <p class="text-muted">Email: <a href="mailto:info@draprilleventura.com" class="vd-link">info@draprilleventura.com</a></p>
        </div>
        <div class="col-12 col-md-5">
          <div class="card vd-form-card p-4 border">
            <form action="mailto:info@draprilleventura.com" method="POST" enctype="text/plain">
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
      <p class="mb-0 small text-white">
        &copy; <script>document.write(new Date().getFullYear())</script> Dr. Aprille Ventura Clinica Dental. All rights reserved.
      </p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
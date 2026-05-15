<?php
  require_once '../../config/conn.php';
  require_once '../models/clinicModel.php';

  $db = new Database();
  $conn = $db->connect();
  $clinicModel = new Clinic($conn);
  $clinics = $clinicModel->getAllClinics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book an Appointment | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body class="vd-form-page py-5">

  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-8">
        <div class="card vd-page-card border p-4 p-md-5">

          <!-- HEADER -->
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-3 pb-4 mb-4" style="border-bottom:1px solid #d9c9a8;">
            <div>
              <div class="vd-logo-name">Dr. Aprille</div>
              <div class="vd-logo-ventura">VEN<span class="vd-cross">✚</span>URA</div>
              <div class="vd-logo-sub">Clinica Dental</div>
            </div>
            <div class="text-sm-end vd-clinic-meta">
              <strong>DR. APRILLE CABAYU VENTURA</strong><br>
              Alcala Branch – Zone 4, Tupang, Alcala, Cagayan<br>
              Tuguegarao Branch – Bartolome St., Tuguegarao City<br>
              📞 09157631034 &nbsp;|&nbsp; Mon–Sat, 10am–4pm
            </div>
          </div>

          <!-- BOOKING FORM -->
          <div id="formView">
            <h1 class="vd-page-title mb-1">Book an Appointment</h1>
            <p class="text-muted small mb-4">Please fill in your details below. Our team will confirm your appointment within 24 hours.</p>

            <form id="bookingForm" novalidate>

              <!-- PATIENT DETAILS -->
              <p class="vd-section-label">Patient Details</p>
              <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                  <label class="vd-label form-label">Last Name</label>
                  <input type="text" name="lastname" class="form-control vd-input" placeholder="Dela Cruz" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="vd-label form-label">First Name</label>
                  <input type="text" name="firstname" class="form-control vd-input" placeholder="Juan" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="vd-label form-label">Middle Name</label>
                  <input type="text" name="middlename" class="form-control vd-input" placeholder="Santos">
                </div>
                <div class="col-6 col-md-4">
                  <label class="vd-label form-label">Age</label>
                  <input type="number" name="age" class="form-control vd-input" min="1" max="120" placeholder="25" required>
                </div>
                <div class="col-6 col-md-4">
                  <label class="vd-label form-label">Gender</label>
                  <select name="gender" class="form-select vd-input" required>
                    <option value="" disabled selected>— Select —</option>
                    <option>Male</option>
                    <option>Female</option>
                    <option>Prefer not to say</option>
                  </select>
                </div>
                <div class="col-12 col-md-6">
                  <label class="vd-label form-label">Phone Number</label>
                  <input type="tel" name="phone_number" class="form-control vd-input" placeholder="09XX XXX XXXX" required>
                </div>
                <div class="col-12 col-md-6">
                  <label class="vd-label form-label">Email Address</label>
                  <input type="email" name="email" class="form-control vd-input" placeholder="email@example.com">
                </div>
              </div>

              <!-- SELECT CLINIC -->
              <p class="vd-section-label">Select Clinic</p>
              <div class="row g-3 mb-3">
                <?php foreach ($clinics as $clinic): ?>
                  <div class="col-12 col-sm-6">
                    <label class="vd-clinic-card w-100">
                      <input type="radio" name="clinic" value="<?= htmlspecialchars($clinic['clinic_name']) ?>" class="d-none vd-clinic-radio" required>
                      <div class="vd-clinic-card-inner p-3 rounded">
                        <div class="vd-clinic-tag"><?= htmlspecialchars($clinic['clinic_name']) ?></div>
                        <div class="vd-clinic-address"><?= htmlspecialchars($clinic['clinic_address']) ?></div>
                        <div class="vd-clinic-address">📞 <?= htmlspecialchars($clinic['clinic_contact']) ?></div>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- SELECT SERVICE -->
              <p class="vd-section-label">Select Service</p>
              <div class="mb-3">
                <label class="vd-label form-label">Dental Service</label>
                <select name="service" class="form-select vd-input" required>
                  <option value="" disabled selected>— Choose a service —</option>
                  <optgroup label="Preventive">
                    <option value="checkup">Dental Check-up / Consultation</option>
                    <option value="cleaning">Teeth Cleaning (Prophylaxis)</option>
                    <option value="fluoride">Fluoride Treatment</option>
                    <option value="xray">Dental X-Ray</option>
                  </optgroup>
                  <optgroup label="Restorative">
                    <option value="filling">Tooth Filling (Composite / Amalgam)</option>
                    <option value="crown">Dental Crown</option>
                    <option value="rct">Root Canal Treatment</option>
                    <option value="denture">Dentures</option>
                  </optgroup>
                  <optgroup label="Surgical">
                    <option value="extraction">Tooth Extraction</option>
                    <option value="surgical-extraction">Surgical Extraction</option>
                    <option value="implant">Dental Implant</option>
                  </optgroup>
                  <optgroup label="Cosmetic">
                    <option value="whitening">Teeth Whitening</option>
                    <option value="veneer">Dental Veneers</option>
                    <option value="braces">Orthodontic Braces / Retainer</option>
                  </optgroup>
                  <optgroup label="Other">
                    <option value="pediatric">Pediatric Dentistry</option>
                    <option value="emergency">Emergency / Pain Relief</option>
                    <option value="other">Other (Please specify at your visit)</option>
                  </optgroup>
                </select>
              </div>

              <!-- DATE & TIME -->
              <p class="vd-section-label">Preferred Schedule</p>
              <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6">
                  <label class="vd-label form-label">Preferred Date</label>
                  <input type="date" id="prefDate" name="date" class="form-control vd-input" required>
                </div>
                <div class="col-12 col-sm-6">
                  <label class="vd-label form-label">Preferred Time Slot</label>
                  <select name="time" class="form-select vd-input" required>
                    <option value="" disabled selected>— Select time —</option>
                    <option value="10:00">10:00 AM</option>
                    <option value="10:30">10:30 AM</option>
                    <option value="11:00">11:00 AM</option>
                    <option value="11:30">11:30 AM</option>
                    <option value="12:00">12:00 PM</option>
                    <option value="13:00">1:00 PM</option>
                    <option value="13:30">1:30 PM</option>
                    <option value="14:00">2:00 PM</option>
                    <option value="14:30">2:30 PM</option>
                    <option value="15:00">3:00 PM</option>
                    <option value="15:30">3:30 PM</option>
                  </select>
                </div>
              </div>

              <!-- NOTICE -->
              <div class="vd-notice d-flex gap-2 p-3 mb-4 rounded">
                <span class="vd-notice-icon">ℹ</span>
                <span class="small">All appointments are <strong>strictly by appointment only</strong> and subject to confirmation. A member of our team will reach out via email within 24 hours to confirm your slot.</span>
              </div>

              <input type="hidden" name="action" value="book">

              <!-- ACTIONS -->
              <div class="d-flex justify-content-end gap-2 pt-4" style="border-top:1px solid #d9c9a8;">
                <button type="button" class="btn vd-btn-outline" onclick="document.getElementById('bookingForm').reset()">Clear Form</button>
                <button type="submit" class="btn vd-btn-gold px-5">Request Appointment</button>
              </div>

            </form>
          </div><!-- /formView -->

          <!-- SUCCESS STATE -->
          <div id="successView" class="d-none text-center py-5">
            <div class="vd-success-icon mx-auto mb-4">✓</div>
            <h2 class="vd-page-title mb-2">Appointment Requested</h2>
            <p class="text-muted small mb-4">Thank you! Your request has been submitted. We'll confirm your schedule within 24 hours.</p>
            <div class="vd-booking-ref d-inline-block mb-4" id="refNumber">VCD-000000</div>
            <br>
            <button class="btn vd-btn-gold px-4" onclick="resetForm()">Book Another</button>
          </div>

        </div><!-- /card -->
      </div>
    </div>
  </div>

  <!-- ACCOUNT CREATION MODAL -->
  <div id="accountModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content vd-modal-bs p-4">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5 class="vd-modal-title mb-0">Create an Account?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <p class="text-muted small mb-4">Would you like to create an account to manage your appointments and view your booking history?</p>
        <div class="d-flex justify-content-end gap-2">
          <button class="btn vd-btn-outline" onclick="skipAccount()">No, thanks</button>
          <button class="btn vd-btn-gold" onclick="proceedAccount()">Yes, create account</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>

    document.querySelectorAll('.vd-clinic-radio').forEach(radio => {
      radio.addEventListener('change', function() {
        document.querySelectorAll('.vd-clinic-card-inner').forEach(c => c.classList.remove('selected'));
        this.closest('.vd-clinic-card').querySelector('.vd-clinic-card-inner').classList.add('selected');
      });
    });
    
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
      e.preventDefault();
      accountModal.show();
    });

    function skipAccount() {
      accountModal.hide();
      submitBooking();
    }

    function proceedAccount() {
      accountModal.hide();
      submitBooking(true);
    }

    async function submitBooking(redirect = false) {
      const formData = new FormData(document.getElementById('bookingForm'));

      const response = await fetch('../../apps/controllers/appointmentController.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.success) {
        document.getElementById('refNumber').textContent = 'VCD-' + result.appointment_id;
        document.getElementById('formView').classList.add('d-none');
        document.getElementById('successView').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });

        if (redirect) {
          window.location.href = 'ventura_dental_form.php';
        }
      } else {
        alert(result.message);
      }
    }
  </script>
</body>
</html>
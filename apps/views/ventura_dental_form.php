<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Form | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body class="vd-form-page py-5">

  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-xl-10">
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
              Tuguegarao Branch – Bartolome St., Caggu, Tuguegarao City, Cagayan<br>
              📞 09157631034 &nbsp;|&nbsp; Mon–Sat, 10am–4pm<br>
              Strictly by Appointment
            </div>
          </div>

          <form id="patientForm" novalidate>

            <!-- PATIENT INFORMATION -->
            <p class="vd-section-label">Patient Information</p>
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Last Name</label>
                <input type="text" id="lastName" name="lastName" class="form-control vd-input" placeholder="Dela Cruz" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">First Name</label>
                <input type="text" id="firstName" name="firstName" class="form-control vd-input" placeholder="Juan" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Middle Name</label>
                <input type="text" id="middleName" name="middleName" class="form-control vd-input" placeholder="Santos">
              </div>

              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Birthdate</label>
                <input type="date" id="birthdate" name="birthdate" class="form-control vd-input" required>
              </div>
              <div class="col-6 col-md-4">
                <label class="vd-label form-label">Age</label>
                <input type="number" id="age" name="age" min="0" max="120" class="form-control vd-input" placeholder="25">
              </div>
              <div class="col-6 col-md-4">
                <label class="vd-label form-label">Sex</label>
                <select id="sex" name="sex" class="form-select vd-input" required>
                  <option value="" disabled selected>— Select —</option>
                  <option>Male</option>
                  <option>Female</option>
                  <option>Prefer not to say</option>
                </select>
              </div>

              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Civil Status</label>
                <select id="civilStatus" name="civilStatus" class="form-select vd-input">
                  <option value="" disabled selected>— Select —</option>
                  <option>Single</option>
                  <option>Married</option>
                  <option>Widowed</option>
                  <option>Separated</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Mobile Number</label>
                <input type="tel" id="mobile" name="mobile" class="form-control vd-input" placeholder="09XX XXX XXXX" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control vd-input" placeholder="email@example.com">
              </div>

              <div class="col-12">
                <label class="vd-label form-label">Home Address</label>
                <input type="text" id="homeAddress" name="homeAddress" class="form-control vd-input" placeholder="Street, Barangay, City">
              </div>
              <div class="col-12">
                <label class="vd-label form-label">Work Address</label>
                <input type="text" id="workAddress" name="workAddress" class="form-control vd-input" placeholder="Street, Barangay, City">
              </div>

              <div class="col-12 col-md-4">
                <label class="vd-label form-label">FB Account</label>
                <input type="text" id="fbAccount" name="fbAccount" class="form-control vd-input" placeholder="facebook.com/...">
              </div>
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Occupation</label>
                <input type="text" id="occupation" name="occupation" class="form-control vd-input" placeholder="e.g. Teacher">
              </div>
              <div class="col-12 col-md-4">
                <label class="vd-label form-label">Office Contact Number</label>
                <input type="tel" id="officeContact" name="officeContact" class="form-control vd-input" placeholder="09XX XXX XXXX">
              </div>
            </div>

            <!-- FOR MINORS -->
            <div class="vd-minors-box p-3 p-md-4 rounded mb-4" id="minorsBox" style="display:none;">
              <div class="vd-minors-label mb-3">For Minors</div>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="vd-label form-label">Parent / Guardian's Name</label>
                  <input type="text" id="guardianName" name="guardianName" class="form-control vd-input" placeholder="Full name">
                </div>
                <div class="col-12 col-md-6">
                  <label class="vd-label form-label">Contact Number</label>
                  <input type="tel" id="guardianContact" name="guardianContact" class="form-control vd-input" placeholder="09XX XXX XXXX">
                </div>
                <div class="col-12 col-md-6">
                  <label class="vd-label form-label">Name of Physician</label>
                  <input type="text" id="physicianName" name="physicianName" class="form-control vd-input" placeholder="Dr.">
                </div>
                <div class="col-12 col-md-6">
                  <label class="vd-label form-label">Physician Contact Number</label>
                  <input type="tel" id="physicianContact" name="physicianContact" class="form-control vd-input" placeholder="09XX XXX XXXX">
                </div>
                <div class="col-12">
                  <label class="vd-label form-label">Physician Address</label>
                  <input type="text" id="physicianAddress" name="physicianAddress" class="form-control vd-input" placeholder="Clinic address">
                </div>
              </div>
            </div>

            <!-- DENTAL HISTORY -->
            <p class="vd-section-label">Dental History</p>
            <div class="row g-3 mb-4">
              <div class="col-12 col-md-6">
                <label class="vd-label form-label">Previous Dentist</label>
                <input type="text" id="previousDentist" name="previousDentist" class="form-control vd-input" placeholder="Dr.">
              </div>
              <div class="col-12 col-md-6">
                <label class="vd-label form-label">Last Dental Visit</label>
                <input type="date" id="lastDentalVisit" name="lastDentalVisit" class="form-control vd-input">
              </div>
              <div class="col-12">
                <label class="vd-label form-label">Treatment Done</label>
                <input type="text" id="treatmentDone" name="treatmentDone" class="form-control vd-input" placeholder="e.g. Cleaning, Extraction">
              </div>
              <div class="col-12 col-md-6">
                <label class="vd-label form-label">Reason for Dental Visit</label>
                <input type="text" id="reasonForVisit" name="reasonForVisit" class="form-control vd-input" placeholder="e.g. Check-up, Pain">
              </div>
              <div class="col-12 col-md-6">
                <label class="vd-label form-label">Referred by</label>
                <input type="text" id="referredBy" name="referredBy" class="form-control vd-input" placeholder="Name or source">
              </div>
            </div>

            <!-- HEALTH QUESTIONNAIRE -->
            <p class="vd-section-label">Health Questionnaire</p>
            <p class="text-muted small mb-3">Please select your answer for each question.</p>

            <div class="table-responsive mb-4">
              <table class="table vd-hq-table">
                <thead>
                  <tr>
                    <th></th>
                    <th class="text-center" style="width:80px;">Yes</th>
                    <th class="text-center" style="width:80px;">No</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Are you in good health?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="goodHealth" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="goodHealth" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Are you under medical condition right now?<br><input class="follow-up vd-input form-control mt-1" type="text" name="medicalConditionDetail" placeholder="If yes, specify condition…" disabled></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="medicalCondition" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="medicalCondition" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Have you ever had serious illness or surgical operation?<br><input class="follow-up vd-input form-control mt-1" type="text" name="seriousIllnessDetail" placeholder="If yes, specify…" disabled></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="seriousIllness" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="seriousIllness" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Have you ever been hospitalized?<br><input class="follow-up vd-input form-control mt-1" type="text" name="hospitalizedDetail" placeholder="If yes, specify…" disabled></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="hospitalized" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="hospitalized" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Are you taking any medication?<br><input class="follow-up vd-input form-control mt-1" type="text" name="medicationDetail" placeholder="If yes, specify medication…" disabled></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="medication" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="medication" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Do you smoke?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="smoke" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="smoke" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Do you use alcohol?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="alcohol" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="alcohol" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Do you use drugs?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="drugs" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="drugs" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Are you allergic to any of the following? (Local Anesthetics, Latex, Penicillin, Aspirin, others)<br><input class="follow-up vd-input form-control mt-1" type="text" name="allergyDetail" placeholder="Specify allergens…" disabled></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="allergy" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="allergy" value="no"></div></td>
                  </tr>
                  <!-- Women only -->
                  <tr class="table-light">
                    <td colspan="3" class="py-2" style="font-size:10px;letter-spacing:0.12em;color:#b5924c;font-weight:500;text-transform:uppercase;">For Women Only</td>
                  </tr>
                  <tr>
                    <td>Are you pregnant?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="pregnant" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="pregnant" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Are you nursing?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="nursing" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="nursing" value="no"></div></td>
                  </tr>
                  <tr>
                    <td>Are you taking birth control pills?</td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="birthControl" value="yes"></div></td>
                    <td class="text-center"><div class="form-check d-flex justify-content-center"><input class="form-check-input vd-radio" type="radio" name="birthControl" value="no"></div></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- CONDITIONS CHECKLIST -->
            <p class="vd-section-label">Please Check If You Have Any of the Following Conditions</p>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-1 mb-4">
              <?php
              $conditions = [
                'High Blood Pressure','Heart Disease','Anemia',
                'Low Blood Pressure','Heart Murmur','Angina',
                'Epilepsy/Convulsions','Hepatitis/Liver Diseases','Asthma',
                'AIDS or HIV Infection','Rheumatic Fever','Emphysema',
                'Sexually Transmitted Disease','Hay Fever/Allergies','Bleeding Problems',
                'Stomach Ulcers','Respiratory Problems','Blood Diseases',
                'Fainting/Seizures','Hepatitis/Jaundice','Head Injuries',
                'Rapid Weight Loss','Tuberculosis','Arthritis/Rheumatism',
                'Joint Replacement','Swollen Ankles','Stroke',
                'Heart Surgery','Kidney Disease','Cancer/Tumors',
                'Heart Attack','Diabetes','G6PD',
                'Thyroid Problem','Chest Pain'
              ];
              foreach ($conditions as $cond):
              ?>
              <div class="col">
                <label class="vd-check-item d-flex align-items-center gap-2 py-2 px-1">
                  <input class="form-check-input vd-checkbox m-0" type="checkbox" name="cond" value="<?= htmlspecialchars($cond) ?>">
                  <span class="small"><?= htmlspecialchars($cond) ?></span>
                </label>
              </div>
              <?php endforeach; ?>
              <div class="col-12 col-md-4 mt-2">
                <label class="vd-label form-label">Others</label>
                <input type="text" id="condOthers" name="condOthers" class="form-control vd-input" placeholder="Specify…">
              </div>
            </div>

            <!-- CONSENT -->
            <div class="vd-consent-box p-4 rounded mb-4">
              <p class="small mb-3">
                I, <input class="vd-consent-name vd-input" type="text" name="consentName" placeholder="Full Name" required>,
                do hereby consent to the performance upon:
              </p>
              <div class="d-flex flex-wrap gap-3 mb-3">
                <label class="form-check-label d-flex align-items-center gap-2 small">
                  <input class="form-check-input vd-checkbox m-0" type="checkbox" name="consentFor" onclick="onlyOne(this)" value="myself"> Myself
                </label>
                <label class="form-check-label d-flex align-items-center gap-2 small">
                  <input class="form-check-input vd-checkbox m-0" type="checkbox" name="consentFor" onclick="onlyOne(this)" value="spouse"> Spouse
                </label>
                <label class="form-check-label d-flex align-items-center gap-2 small">
                  <input class="form-check-input vd-checkbox m-0" type="checkbox" name="consentFor" onclick="onlyOne(this)" value="son"> Son
                </label>
                <label class="form-check-label d-flex align-items-center gap-2 small">
                  <input class="form-check-input vd-checkbox m-0" type="checkbox" name="consentFor" onclick="onlyOne(this)" value="daughter"> Daughter
                </label>
                <label class="form-check-label d-flex align-items-center gap-2 small">
                  <input class="form-check-input vd-checkbox m-0" type="checkbox" name="consentFor" onclick="onlyOne(this)" value="others"> Others
                </label>
              </div>
              <p class="small mb-3">of all dental procedures, operations and/or treatment that may be considered necessary to restore my/his/her oral and dental health.</p>
              <p class="small"><strong>This consent is given voluntarily</strong> and whatever result of any intervention or treatment may be, I absolve my dentist from liability. Be it known further, that I'm willing to pay for all services rendered to me and/or my family.</p>

              <!-- FIXME: change sig-line to input:text for signature -->
              <div class="row g-4 mt-3">
                <div class="col-12 col-md-5">
                  <div style="border-bottom:1px solid #1a1612; height:36px;"></div>
                  <p class="text-center small text-muted mt-1" style="font-size:9px;letter-spacing:0.18em;text-transform:uppercase;">Signature of Patient / Guardian</p>
                </div>
                <div class="col-12 col-md-5">
                  <div style="border-bottom:1px solid #1a1612; height:36px;"></div>
                  <p class="text-center small text-muted mt-1" style="font-size:9px;letter-spacing:0.18em;text-transform:uppercase;">Dentist's Signature</p>
                </div>
                <div class="col-12 col-md-2">
                  <div style="border-bottom:1px solid #1a1612; height:36px;"></div>
                  <p class="text-center small text-muted mt-1" style="font-size:9px;letter-spacing:0.18em;text-transform:uppercase;">Date</p>
                </div>
              </div>
            </div>

            <!-- SUBMIT -->
            <div class="text-center mt-4">
              <button type="submit" class="btn vd-btn-gold px-5">Submit Patient Form</button>
            </div>

          </form>
        </div><!-- /card -->
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('patientForm').addEventListener('submit', function(e) {
      e.preventDefault();
      alert('Form submitted successfully!');
      window.location.href = 'registration-form.php';
    });

    function showMinorBoxAndAge() {
      const birthdateInput = document.getElementById('birthdate');
      const ageInput = document.getElementById('age');
      const minorBox = document.getElementById('minorsBox');
      const birthdate = birthdateInput.value;
      if (birthdate) {
        const age = calculateAge(new Date(birthdate));
        if (!isNaN(age)) ageInput.value = age; else ageInput.value = '';
        minorBox.style.display = age < 18 ? 'block' : 'none';
      } else {
        ageInput.value = '';
        minorBox.style.display = 'none';
      }
    }

    function calculateAge(birthdate) {
      const today = new Date();
      let age = today.getFullYear() - birthdate.getFullYear();
      const m = today.getMonth() - birthdate.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) age--;
      return age;
    }

    function setupFollowUps() {
      document.querySelectorAll('.follow-up').forEach(function(followUpInput) {
        let parentRow = followUpInput.closest('tr');
        if (!parentRow) return;
        let radioName = followUpInput.name.replace('Detail', '');
        let radios = parentRow.querySelectorAll('input[type="radio"][name="' + radioName + '"]');
        followUpInput.disabled = true;
        radios.forEach(function(radio) {
          radio.addEventListener('change', function() {
            if (this.value === 'yes' && this.checked) {
              followUpInput.disabled = false;
            } else if (this.value === 'no' && this.checked) {
              followUpInput.disabled = true;
              followUpInput.value = '';
            }
          });
          if (radio.value === 'yes' && radio.checked) followUpInput.disabled = false;
          if (radio.value === 'no' && radio.checked) { followUpInput.disabled = true; followUpInput.value = ''; }
        });
      });
    }

    function onlyOne(checkbox) {
      document.getElementsByName('consentFor').forEach(item => {
        if (item !== checkbox) item.checked = false;
      });
    }

    window.addEventListener('DOMContentLoaded', function() {
      setupFollowUps();
      showMinorBoxAndAge();
    });

    document.getElementById('birthdate').addEventListener('change', showMinorBoxAndAge);
    document.getElementById('birthdate').addEventListener('input', showMinorBoxAndAge);
  </script>
</body>
</html>
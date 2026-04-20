<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dr. Aprille Ventura – Clinica Dental | Patient Form</title>
<link rel="stylesheet" href="../../public/css/form.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
<div class="page">

  <!-- HEADER -->
  <header class="header">
    <div class="logo-block">
      <span class="logo-name">Dr. Aprille</span>
      <div class="logo-ventura">
        VEN<span class="cross">✚</span>URA
      </div>
      <div class="logo-sub">Clinica Dental</div>
    </div>
    <div class="clinic-info">
      <strong>DR. APRILLE CABAYU VENTURA</strong>
      Alcala Branch – Zone 4, Tupang, Alcala, Cagayan<br>
      Tuguegarao Branch – Bartolome St., Caggu, Tuguegarao City, Cagayan<br>
      📞 09157631034 &nbsp;|&nbsp; Mon–Sat, 10am–4pm<br>
      Strictly by Appointment
    </div>
  </header>

  <form id="patientForm" onsubmit="handleSubmit(event)">

    <!-- PATIENT INFORMATION -->
    <div class="section-title">Patient Information</div>

    <div class="grid g-3">
      <div class="field">
        <label for="lastName">Last Name</label>
        <input type="text" id="lastName" name="lastName" placeholder="Dela Cruz" required>
      </div>
      <div class="field">
        <label for="firstName">First Name</label>
        <input type="text" id="firstName" name="firstName" placeholder="Juan" required>
      </div>
      <div class="field">
        <label for="middleName">Middle Name</label>
        <input type="text" id="middleName" name="middleName" placeholder="Santos">
      </div>

      <div class="field">
        <label for="birthdate">Birthdate</label>
        <input type="date" id="birthdate" name="birthdate" required>
      </div>
      <div class="field">
        <label for="age">Age</label>
        <input type="number" id="age" name="age" min="0" max="120" placeholder="25">
      </div>
      <div class="field">
        <label for="sex">Sex</label>
        <select id="sex" name="sex" required>
          <option value="" disabled selected>— Select —</option>
          <option>Male</option>
          <option>Female</option>
          <option>Prefer not to say</option>
        </select>
      </div>

      <div class="field">
        <label for="civilStatus">Civil Status</label>
        <select id="civilStatus" name="civilStatus">
          <option value="" disabled selected>— Select —</option>
          <option>Single</option>
          <option>Married</option>
          <option>Widowed</option>
          <option>Separated</option>
        </select>
      </div>
      <div class="field">
        <label for="mobile">Mobile Number</label>
        <input type="tel" id="mobile" name="mobile" placeholder="09XX XXX XXXX" required>
      </div>
      <div class="field">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="email@example.com">
      </div>

      <div class="field span-full">
        <label for="homeAddress">Home Address</label>
        <input type="text" id="homeAddress" name="homeAddress" placeholder="Street, Barangay, City">
      </div>
      <div class="field span-full">
        <label for="workAddress">Work Address</label>
        <input type="text" id="workAddress" name="workAddress" placeholder="Street, Barangay, City">
      </div>

      <div class="field">
        <label for="fbAccount">FB Account</label>
        <input type="text" id="fbAccount" name="fbAccount" placeholder="facebook.com/...">
      </div>
      <div class="field">
        <label for="occupation">Occupation</label>
        <input type="text" id="occupation" name="occupation" placeholder="e.g. Teacher">
      </div>
      <div class="field">
        <label for="officeContact">Office Contact Number</label>
        <input type="tel" id="officeContact" name="officeContact" placeholder="09XX XXX XXXX">
      </div>
    </div>

    <!-- FOR MINORS -->
    <div class="minors-box">
      <div class="minors-label">For Minors</div>
      <div class="grid g-2">
        <div class="field">
          <label for="guardianName">Parent / Guardian's Name</label>
          <input type="text" id="guardianName" name="guardianName" placeholder="Full name">
        </div>
        <div class="field">
          <label for="guardianContact">Contact Number</label>
          <input type="tel" id="guardianContact" name="guardianContact" placeholder="09XX XXX XXXX">
        </div>
        <div class="field">
          <label for="physicianName">Name of Physician</label>
          <input type="text" id="physicianName" name="physicianName" placeholder="Dr. ">
        </div>
        <div class="field">
          <label for="physicianContact">Physician Contact Number</label>
          <input type="tel" id="physicianContact" name="physicianContact" placeholder="09XX XXX XXXX">
        </div>
        <div class="field span-2">
          <label for="physicianAddress">Physician Address</label>
          <input type="text" id="physicianAddress" name="physicianAddress" placeholder="Clinic address">
        </div>
      </div>
    </div>

    <!-- DENTAL HISTORY -->
    <div class="section-title">Dental History</div>

    <div class="grid g-2">
      <div class="field">
        <label for="previousDentist">Previous Dentist</label>
        <input type="text" id="previousDentist" name="previousDentist" placeholder="Dr. ">
      </div>
      <div class="field">
        <label for="lastDentalVisit">Last Dental Visit</label>
        <input type="date" id="lastDentalVisit" name="lastDentalVisit">
      </div>
      <div class="field span-2">
        <label for="treatmentDone">Treatment Done</label>
        <input type="text" id="treatmentDone" name="treatmentDone" placeholder="e.g. Cleaning, Extraction">
      </div>
      <div class="field">
        <label for="reasonForVisit">Reason for Dental Visit</label>
        <input type="text" id="reasonForVisit" name="reasonForVisit" placeholder="e.g. Check-up, Pain">
      </div>
      <div class="field">
        <label for="referredBy">Referred by</label>
        <input type="text" id="referredBy" name="referredBy" placeholder="Name or source">
      </div>
    </div>

    <!-- HEALTH QUESTIONNAIRE -->
    <div class="section-title">Health Questionnaire</div>
    <p style="font-size:11px;color:var(--mid);margin-bottom:18px;letter-spacing:0.04em;">Please select your answer for each question.</p>

    <table class="hq-table">
      <thead>
        <tr>
          <th></th>
          <th>Yes</th>
          <th>No</th>
        </tr>
      </thead>
      <tbody>

        <!-- Simple Y/N rows -->
        <tr>
          <td>Are you in good health?</td>
          <td><div class="yn-radio"><label><input type="radio" name="goodHealth" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="goodHealth" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>
            Are you under medical condition right now?
            <br><input class="follow-up" type="text" name="medicalConditionDetail" placeholder="If yes, specify condition…">
          </td>
          <td><div class="yn-radio"><label><input type="radio" name="medicalCondition" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="medicalCondition" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>
            Have you ever had serious illness or surgical operation?
            <br><input class="follow-up" type="text" name="seriousIllnessDetail" placeholder="If yes, specify…">
          </td>
          <td><div class="yn-radio"><label><input type="radio" name="seriousIllness" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="seriousIllness" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>
            Have you ever been hospitalized?
            <br><input class="follow-up" type="text" name="hospitalizedDetail" placeholder="If yes, specify…">
          </td>
          <td><div class="yn-radio"><label><input type="radio" name="hospitalized" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="hospitalized" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>
            Are you taking any medication?
            <br><input class="follow-up" type="text" name="medicationDetail" placeholder="If yes, specify medication…">
          </td>
          <td><div class="yn-radio"><label><input type="radio" name="medication" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="medication" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>Do you smoke?</td>
          <td><div class="yn-radio"><label><input type="radio" name="smoke" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="smoke" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>Do you use alcohol?</td>
          <td><div class="yn-radio"><label><input type="radio" name="alcohol" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="alcohol" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>Do you use drugs?</td>
          <td><div class="yn-radio"><label><input type="radio" name="drugs" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="drugs" value="no"> No</label></div></td>
        </tr>
        <tr>
          <td>
            Are you allergic to any of the following? (Local Anesthetics, Latex, Penicillin, Aspirin, others)
            <br><input class="follow-up" type="text" name="allergyDetail" placeholder="Specify allergens…">
          </td>
          <td><div class="yn-radio"><label><input type="radio" name="allergy" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="allergy" value="no"> No</label></div></td>
        </tr>

        <!-- Women only -->
        <tr class="sub-q">
          <td colspan="3" style="padding-top:14px;padding-bottom:4px;font-size:10px;letter-spacing:0.12em;color:var(--gold);font-weight:500;text-transform:uppercase;">For Women Only</td>
        </tr>
        <tr class="sub-q">
          <td>Are you pregnant?</td>
          <td><div class="yn-radio"><label><input type="radio" name="pregnant" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="pregnant" value="no"> No</label></div></td>
        </tr>
        <tr class="sub-q">
          <td>Are you nursing?</td>
          <td><div class="yn-radio"><label><input type="radio" name="nursing" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="nursing" value="no"> No</label></div></td>
        </tr>
        <tr class="sub-q">
          <td>Are you taking birth control pills?</td>
          <td><div class="yn-radio"><label><input type="radio" name="birthControl" value="yes"> Yes</label></div></td>
          <td><div class="yn-radio"><label><input type="radio" name="birthControl" value="no"> No</label></div></td>
        </tr>

      </tbody>
    </table>

    <!-- CONDITIONS CHECKLIST -->
    <div class="section-title">Please Check If You Have Any of the Following Conditions</div>

    <div class="conditions-grid">
      <!-- Column 1 -->
      <label class="check-item"><input type="checkbox" name="cond" value="High Blood Pressure"> High Blood Pressure</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Heart Disease"> Heart Disease</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Anemia"> Anemia</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Low Blood Pressure"> Low Blood Pressure</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Heart Murmur"> Heart Murmur</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Angina"> Angina</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Epilepsy/Convulsions"> Epilepsy / Convulsions</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Hepatitis/Liver Diseases"> Hepatitis / Liver Diseases</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Asthma"> Asthma</label>

      <label class="check-item"><input type="checkbox" name="cond" value="AIDS or HIV Infection"> AIDS or HIV Infection</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Rheumatic Fever"> Rheumatic Fever</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Emphysema"> Emphysema</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Sexually Transmitted Disease"> Sexually Transmitted Disease</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Hay Fever/Allergies"> Hay Fever / Allergies</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Bleeding Problems"> Bleeding Problems</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Stomach Ulcers"> Stomach Ulcers</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Respiratory Problems"> Respiratory Problems</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Blood Diseases"> Blood Diseases</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Fainting/Seizures"> Fainting / Seizures</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Hepatitis/Jaundice"> Hepatitis / Jaundice</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Head Injuries"> Head Injuries</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Rapid Weight Loss"> Rapid Weight Loss</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Tuberculosis"> Tuberculosis</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Arthritis/Rheumatism"> Arthritis / Rheumatism</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Joint Replacement"> Joint Replacement</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Swollen Ankles"> Swollen Ankles</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Stroke"> Stroke</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Heart Surgery"> Heart Surgery</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Kidney Disease"> Kidney Disease</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Cancer/Tumors"> Cancer / Tumors</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Heart Attack"> Heart Attack</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Diabetes"> Diabetes</label>
      <label class="check-item"><input type="checkbox" name="cond" value="G6PD"> G6PD</label>

      <label class="check-item"><input type="checkbox" name="cond" value="Thyroid Problem"> Thyroid Problem</label>
      <label class="check-item"><input type="checkbox" name="cond" value="Chest Pain"> Chest Pain</label>

      <!-- Others -->
      <div class="field" style="grid-column: span 1; padding-top: 4px;">
        <label for="condOthers">Others</label>
        <input type="text" id="condOthers" name="condOthers" placeholder="Specify…">
      </div>
    </div>

    <!-- CONSENT -->
    <div class="consent-box">
      <p>I, <input class="consent-name" type="text" name="consentName" placeholder="Full Name" required>, do hereby consent to the performance upon:</p>

      <div class="consent-checkboxes">
        <label><input type="checkbox" name="consentFor" value="myself"> Myself</label>
        <label><input type="checkbox" name="consentFor" value="spouse"> Spouse</label>
        <label><input type="checkbox" name="consentFor" value="son"> Son</label>
        <label><input type="checkbox" name="consentFor" value="daughter"> Daughter</label>
        <label><input type="checkbox" name="consentFor" value="others"> Others</label>
      </div>

      <p>of all dental procedures, operations and/or treatment that may be considered necessary to restore my/his/her oral and dental health.</p>
      <br>
      <p><strong>This consent is given voluntarily</strong> and whatever result of any intervention or treatment may be, I absolve my dentist from liability. Be it known further, that I'm willing to pay for all services rendered to me and/or my family.</p>

      <div class="sig-row">
        <div class="sig-field">
          <div class="sig-line"></div>
          <div class="sig-label">Signature of Patient / Guardian</div>
        </div>
        <div class="sig-field">
          <div class="sig-line"></div>
          <div class="sig-label">Dentist's Signature</div>
        </div>
        <div class="sig-field">
          <div class="sig-line"></div>
          <div class="sig-label">Date</div>
        </div>
      </div>
    </div>

    <div class="submit-row">
      <button type="submit">Submit Patient Form</button>
    </div>

  </form>
</div>

<script>
  function handleSubmit(e) {
    e.preventDefault();
    alert('Form submitted successfully! Please present this to the clinic staff.');
  }

  function showMinorBoxAndAge() {
    const birthdateInput = document.getElementById('birthdate');
    const ageInput = document.getElementById('age');
    const minorBox = document.querySelector('.minors-box');
    const birthdate = birthdateInput.value;
    if (birthdate) {
      const age = calculateAge(new Date(birthdate));
      if (!isNaN(age)) {
        ageInput.value = age;
      } else {
        ageInput.value = '';
      }
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
    if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
      age--;
    }
    return age;
  }

  function setupFollowUps() {
    // For each follow-up input, set up listeners on its corresponding radios
    document.querySelectorAll('.follow-up').forEach(function(followUpInput) {
      // Find the parent <tr> (or closest parent element containing the radios)
      let parentRow = followUpInput.closest('tr');
      if (!parentRow) return;
      // Find the radio buttons for this question by name
      // The name is the same as the follow-up input's name, minus 'Detail' (e.g., medicalConditionDetail -> medicalCondition)
      let inputName = followUpInput.name;
      let radioName = inputName.replace('Detail', '');
      let radios = parentRow.querySelectorAll('input[type="radio"][name="' + radioName + '"]');
      // Disable by default
      followUpInput.disabled = true;
      // Add listeners
      radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
          if (this.value === 'yes' && this.checked) {
            followUpInput.disabled = false;
          } else if (this.value === 'no' && this.checked) {
            followUpInput.disabled = true;
            followUpInput.value = '';
          }
        });
        // Initial state
        if (radio.value === 'yes' && radio.checked) {
          followUpInput.disabled = false;
        }
        if (radio.value === 'no' && radio.checked) {
          followUpInput.disabled = true;
          followUpInput.value = '';
        }
      });
    });
  }

  window.addEventListener('DOMContentLoaded', function() {
    setupFollowUps();
  });
  document.getElementById('birthdate').addEventListener('change', showMinorBoxAndAge);
  // Also update age if user types in the field manually
  document.getElementById('birthdate').addEventListener('input', showMinorBoxAndAge);
  showMinorBoxAndAge(); // Check initial state on page load
</script>
</body>
</html>

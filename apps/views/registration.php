<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr. Aprille Ventura Clinica Dental || Registration
    </title>
    <link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <img src="../../public/assets/logo.png" alt="Dr. Aprille Ventura Clinica Dental Logo" class="logo">
        </div>
    </nav>
    <section id="registration">
        <div class="registration-container">
            <h2>Patient Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="lastname">Last Name:</label>
                    <input type="text" id="lastname" name="lastname" required>
                </div>
                <div class="form-group">
                    <label for="firstname">First Name:</label>
                    <input type="text" id="firstname" name="firstname" required>
                </div>
                <div class="form-group">
                    <label for="middlename">Middle Name:</label>
                    <input type="text" id="middlename" name="middlename">
                </div>
                <div class="form-group suffix">
                    <label for="suffix">Suffix:</label>
                    <input type="text" id="suffix" name="suffix">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="age-label" for="age">Age:</label>
                    <input type="number" id="age" name="age" required>
                </div>
                <div class="form-group gender-group">
                    <label for="gender">Gender:</label>
                    <div class="select-wrapper">
                        <select id="gender" name="gender" required>
                            <option value="">--Please choose an option--</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                        <img class="select-caret" width="30" height="30" src="../../public/assets/dropdown.png" alt="down-squared"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="civil-status">Civil Status:</label>
                    <div class="select-wrapper">
                        <select id="civil-status" name="civil_status" required>
                            <option value="">--Please choose an option--</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="divorced">Divorced</option>
                            <option value="widowed">Widowed</option>
                        </select>
                        <img class="select-caret" width="30" height="30" src="../../public/assets/dropdown.png" alt="down-squared"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="occupation">Occupation:</label>
                    <input type="text" id="occupation" name="occupation" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group address-group">
                    <label for="address">Address:</label>
                    <div class="address-fields">
                        <input type="text" id="province" name="address" required>
                        <span>,</span>
                        <input type="text" id="municipal" name="address" required>
                        <span>,</span>
                        <input type="text" id="barangay" name="address" required>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>
        </div>

        <div class="registration-container guardian-info" style="display: none;">
            <h2>Guardian Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="guardian-lastname">Last Name:</label>
                    <input type="text" id="guardian-lastname" name="guardian_lastname" required>
                </div>
                <div class="form-group">
                    <label for="guardian-firstname">First Name:</label>
                    <input type="text" id="guardian-firstname" name="guardian_firstname" required>
                </div>
                <div class="form-group">
                    <label for="guardian-middlename">Middle Name:</label>
                    <input type="text" id="guardian-middlename" name="guardian_middlename">
                </div>
                <div class="form-group">
                    <label for="guardian-relationship">Relationship:</label>
                    <div class="select-wrapper">
                        <select id="guardian-relationship" name="guardian_relationship" required>
                            <option value="">--Please choose an option--</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="relative">Relative</option>
                            <option value="other">Other</option>
                            <img class="select-caret" width="30" height="30" src="../../public/assets/dropdown.png" alt="down-squared"/>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="registration-container questionnaire">
            <h2>Medical Questionnaire</h2>
            <div class="form-row">
                <div class="form-group question-group">
                    <label for="q1">Are you in good health?</label>
                    <div class="radio">
                        <input type="radio" id="q1-yes" name="q1" value="yes" required>
                        <label for="q1-yes">Yes</label>
                        <input type="radio" id="q1-no" name="q1" value="no" required>
                        <label for="q1-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <div class="q-label">
                    <label for="q2">Are you under medical condition right now?</label>
                        <div class="specify-input" id="q2-specify" style="display:none;">
                            <label for="q2_specify">If yes, specify:</label>
                            <input type="text" id="q2_specify" name="q2_specify">
                        </div>
                    </div>
                    <div class="radio">
                        <input type="radio" id="q2-yes" name="q2" value="yes" required>
                        <label for="q2-yes">Yes</label>
                        <input type="radio" id="q2-no" name="q2" value="no" required>
                        <label for="q2-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <div class="q-label">
                        <label for="q3">Have you ever had serious illness or surgical operation?</label>
                        <div class="specify-input" id="q3-specify" style="display:none;">
                            <label for="q3_specify">If yes, specify:</label>
                            <input type="text" id="q3_specify" name="q3_specify">
                        </div>
                    </div>
                    <div class="radio">
                        <input type="radio" id="q3-yes" name="q3" value="yes" required>
                        <label for="q3-yes">Yes</label>
                        <input type="radio" id="q3-no" name="q3" value="no" required>
                        <label for="q3-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <div class="q-label">
                    <label for="q4">Have you ever been hospitalized?</label>
                        <div class="specify-input" id="q4-specify" style="display:none;">
                            <label for="q4_specify">If yes, specify:</label>
                            <input type="text" id="q4_specify" name="q4_specify">
                        </div>
                    </div>
                    <div class="radio">
                        <input type="radio" id="q4-yes" name="q4" value="yes" required>
                        <label for="q4-yes">Yes</label>
                        <input type="radio" id="q4-no" name="q4" value="no" required>
                        <label for="q4-no">No</label>
                    </div>
                        
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <div class="q-label">
                    <label for="q5">Are you taking any medication?</label>
                        <div class="specify-input" id="q5-specify" style="display:none;">
                            <label for="q5_specify">If yes, specify:</label>
                            <input type="text" id="q5_specify" name="q5_specify">
                        </div>
                    </div>
                    <div class="radio">
                        <input type="radio" id="q5-yes" name="q5" value="yes" required>
                        <label for="q5-yes">Yes</label>
                        <input type="radio" id="q5-no" name="q5" value="no" required>
                        <label for="q5-no">No</label>
                    </div>
                        
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <label for="q6">Do you smoke?</label>
                    <div class="radio">
                        <input type="radio" id="q6-yes" name="q6" value="yes" required>
                        <label for="q6-yes">Yes</label>
                        <input type="radio" id="q6-no" name="q6" value="no" required>
                        <label for="q6-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <label for="q7">Do you use alcohol?</label>
                    <div class="radio">
                        <input type="radio" id="q7-yes" name="q7" value="yes" required>
                        <label for="q7-yes">Yes</label>
                        <input type="radio" id="q7-no" name="q7" value="no" required>
                        <label for="q7-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <div class="q-label">
                    <label for="q8">Do you use drugs?</label>
                        <div class="specify-input" id="q8-specify" style="display:none;">
                            <label for="q8_specify">If yes, specify:</label>
                            <input type="text" id="q8_specify" name="q8_specify">
                        </div>
                    </div>
                    <div class="radio">
                        <input type="radio" id="q8-yes" name="q8" value="yes" required>
                        <label for="q8-yes">Yes</label>
                        <input type="radio" id="q8-no" name="q8" value="no" required>
                        <label for="q8-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <div class="q-label">
                    <label for="q9">Are you allergic to any of the ff; Local Anesthetics, Latex, Penicillin, Aspirin Others:</label>
                        <div class="specify-input" id="q9-specify" style="display:none;">
                            <label for="q9_specify">If yes, specify:</label>
                            <input type="text" id="q9_specify" name="q9_specify">
                        </div>
                    </div>
                    <div class="radio">
                        <input type="radio" id="q9-yes" name="q9" value="yes" required>
                        <label for="q9-yes">Yes</label>
                        <input type="radio" id="q9-no" name="q9" value="no" required>
                        <label for="q9-no">No</label>
                    </div>
                </div>
            </div>
            <h3>For women only:</h3>
            <div class="form-row">
                <div class="form-group question-group">
                    <label for="q10">Are you pregnant?</label>
                    <div class="radio">
                        <input type="radio" id="q10-yes" name="q10" value="yes" required>
                        <label for="q10-yes">Yes</label>
                        <input type="radio" id="q10-no" name="q10" value="no" required>
                        <label for="q10-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <label for="q11">Are you nursing?</label>
                    <div class="radio">
                        <input type="radio" id="q11-yes" name="q11" value="yes" required>
                        <label for="q11-yes">Yes</label>
                        <input type="radio" id="q11-no" name="q11" value="no" required>
                        <label for="q11-no">No</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group question-group">
                    <label for="q12">Are you taking birth control pills?</label>
                    <div class="radio">
                        <input type="radio" id="q12-yes" name="q12" value="yes" required>
                        <label for="q12-yes">Yes</label>
                        <input type="radio" id="q12-no" name="q12" value="no" required>
                        <label for="q12-no">No</label>
                    </div>
                </div>
            </div>
        </div>
        <!--TODO: continue the form with the following conditions-->
        <!--TODO: Create a digital contract form for the patient to sign before submitting the registration form-->
    </section>
</body>
<script>

// Show/hide specify input fields based on radio selection
function setupConditionalInputs() {
    const questions = [1,2,3,4,5,8,9];
    questions.forEach(function(q) {
        const yesRadio = document.getElementById(`q${q}-yes`);
        const noRadio = document.getElementById(`q${q}-no`);
        const specifyDiv = document.getElementById(`q${q}-specify`);
        if (yesRadio && noRadio && specifyDiv) {
            yesRadio.addEventListener('change', function() {
                if (yesRadio.checked) {
                    specifyDiv.style.display = 'block';
                }
            });
            noRadio.addEventListener('change', function() {
                if (noRadio.checked) {
                    specifyDiv.style.display = 'none';
                }
            });
            // On page load, hide if not yes
            if (!yesRadio.checked) {
                specifyDiv.style.display = 'none';
            } else {
                specifyDiv.style.display = 'block';
            }
        }
    });
}


function guardianInfoToggle() {
    const ageInput = document.getElementById('age');
    const guardianInfo = document.querySelector('.guardian-info');
    ageInput.addEventListener('input', function() {
        if (parseInt(ageInput.value) >= 18) {
            guardianInfo.style.display = 'block';
        } else {
            guardianInfo.style.display = 'none';
        }
    });
}


function noNegativeAge() {
    const ageInput = document.getElementById('age');
    ageInput.addEventListener('input', function() {
        if (ageInput.value < 0) {
            ageInput.value = '';
        }
    });
}

window.addEventListener('DOMContentLoaded', setupConditionalInputs);
window.addEventListener('DOMContentLoaded', guardianInfoToggle);
window.addEventListener('DOMContentLoaded', noNegativeAge);
</script>
</html>
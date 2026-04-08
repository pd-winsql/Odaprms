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
            <h2>Patient Registration</h2>
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
    </section>
</body>
</html>
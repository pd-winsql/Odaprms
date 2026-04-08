<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body>
    <main class="main-content">
        <section id="appointment-section">
            <form action="submit_appointment.php" method="POST" class="appointment-form">
                <img src="../../public/assets/logo.png" alt="Logo" class="logo">
                <h2>Book an Appointment</h2>
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
                <div class="form-group">
                    <label for="Clinic">Select Clinic:</label>
                    <div class="select-wrapper">
                        <select id="clinic" name="clinic" required>
                            <option value="">--Please choose an option--</option>
                            <option value="clinic1">Clinic 1</option>
                            <option value="clinic2">Clinic 2</option>
                        </select>
                        <img class="select-caret" width="30" height="30" src="../../public/assets/dropdown.png" alt="down-squared"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="service">Select Service:</label>
                    <div class="select-wrapper">
                        <select id="service" name="service" required>
                            <option value="">--Please choose an option--</option>
                            <option value="general_dentistry">General Dentistry</option>
                            <option value="cosmetic_dentistry">Cosmetic Dentistry</option>
                            <option value="orthodontics">Orthodontics</option>
                            <option value="pediatric_dentistry">Pediatric Dentistry</option>
                        </select>
                        <img class="select-caret" width="30" height="30" src="../../public/assets/dropdown.png" alt="down-squared"/>
                    </div>
                </div>
                <div class="form-group">
                    <label for="date">Preferred Date:</label>
                    <input type="date" id="date" name="date" required>
                </div>
                <button type="submit" class="btn btn-primary" onclick="openModal('appointmentModal')">Book Appointment</button>
            </form>
        </section>
    </main>
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('appointmentModal')">&times;</span>
            <h2>Appointment Booked Successfully!</h2>
            <p>Thank you for booking an appointment with us. We will contact you shortly to confirm the details.</p>
            <p>Do you want to create an account to manage your appointments and dental records?</p>
            <button class="btn btn-secondary" onclick="openModal('registerModal'); closeModal('appointmentModal')">Yes, Create Account</button>
        </div>
    </div>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        // Close when clicking backdrop
        document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
        });
    </script>
</body>
</html>
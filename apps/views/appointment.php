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
                    <label for="age">Age:</label>
                    <input type="number" id="age" name="age" required>
                </div>
                <div class="form-group gender-group">
                    <label for="gender">Gender:</label>
                    <div class="gender-options">
                        <input type="radio" id="male" name="gender" value="male" required>
                        <label for="male">Male</label>
                        <input type="radio" id="female" name="gender" value="female" required>
                        <label for="female">Female</label>
                        <input type="radio" id="other" name="gender" value="other" required>
                        <label for="other">Other</label>
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
                    <label for="service">Select Service:</label>
                    <select id="service" name="service" required>
                        <option value="">--Please choose an option--</option>
                        <option value="general_dentistry">General Dentistry</option>
                        <option value="cosmetic_dentistry">Cosmetic Dentistry</option>
                        <option value="orthodontics">Orthodontics</option>
                        <option value="pediatric_dentistry">Pediatric Dentistry</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Preferred Date:</label>
                    <input type="date" id="date" name="date" required>
                </div>
                <button type="submit" class="btn btn-primary">Book Appointment</button>
            </form>
        </section>
    </main>
</body>
</html>
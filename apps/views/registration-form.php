<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form | Dr. Aprille Ventura Clinica Dental</title>
    <link rel="stylesheet" href="../../public/css/registration.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>

    <div class="page">
    <form action="registration-form.php" method="POST" class="login-form">
        <img src="../../public/assets/logo.png" alt="Logo" class="logo">
        <h2 class="section-title">Account Registration</h2>
        <div class="form-group">
            <label for="username">Email</label>
            <input type="text" id="email" name="username" required>
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        <!-- TODO: add password strength indicator -->
        <!--TODO: add show password toggle -->
        <div class="form-group"> 
        <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
        <label for="confirm-password">Confirm Password</label>
            <input type="password" id="confirm-password" name="confirm-password" required>
        </div>
        <button type="submit" class="btn-primary">Register</button>
        <div style="text-align:center; margin-top: 16px;">
        <span>Already have an account? </span>
        <a href="apps/views/ventura_dental_form.php" onclick="closeModal('myModal');">Login</a>
        </div>
    </form>
    </div>
</body>
</html>
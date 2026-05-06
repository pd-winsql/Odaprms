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
    <form action="../controllers/users/addUsers.php" method="POST" class="login-form">
        <img src="../../public/assets/logo.png" alt="Logo" class="logo">
        <h2 class="section-title">Account Registration</h2>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
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
        <!--FIXEM: login modal not sure here. better go index then automatic show login modal or 
        just create a separate login form instead of using modal-->
        <a href="../../index.html" id="login-link">Login</a>
        </div>
    </form>
</div>

<script>
    document.getElementById("login-link").addEventListener("click", (e) => {
        e.preventDefault();
        window.location.href = "../../index.html?openModal=true"});
</script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form | Dr. Aprille Ventura Clinica Dental</title>
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body class="vd-form-page d-flex align-items-center justify-content-center min-vh-100 py-5">

    <div class="container">
        <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">

            <div class="card vd-page-card border p-4 p-md-5">
            <form id="registerForm" action="../controllers/userController.php">

                <input type="hidden" name="action" value="register">

                <div class="text-center mb-4">
                <img src="../../public/assets/logo.png" alt="Logo" style="height:90px;">
                </div>

                <p class="vd-section-label mb-4">Account Registration</p>

                <div class="mb-3">
                <label for="email" class="vd-label form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control vd-input" required>
                </div>

                <div class="mb-3">
                <label for="username" class="vd-label form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control vd-input" required>
                </div>

                <div class="mb-3">
                <label for="password" class="vd-label form-label">Password</label>
                <div class="position-relative">
                    <input type="password" id="password" name="password" class="form-control vd-input" required>
                    <button type="button" class="btn btn-sm position-absolute end-0 top-50 translate-middle-y border-0" id="toggle-password" style="color: #b5924c;">
                    <i class="fas fa-eye"></i>
                    </button>
                </div>
                </div>

                <div class="mb-3">
                <label for="confirm-password" class="vd-label form-label">Confirm Password</label>
                <div class="position-relative">
                    <input type="password" id="confirm-password" name="confirm-password" class="form-control vd-input" required>
                    <button type="button" class="btn btn-sm position-absolute end-0 top-50 translate-middle-y border-0" id="toggle-confirm-password" style="color: #b5924c;">
                    <i class="fas fa-eye"></i>
                    </button>
                </div>
                </div>

                <div id="form-error" class="text-danger small mb-2 d-none"></div>

                <button type="submit" class="btn vd-btn-gold w-100 mt-3">Register</button>

                <p class="text-center mt-3 small">
                Already have an account?
                <a href="../../index.html" id="login-link" style="color:#b5924c;">Login</a>
                </p>

            </form>
            </div>

        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('toggle-password');
        const passwordField = document.getElementById('password');
        
        togglePassword.addEventListener('click', (e) => {
        e.preventDefault();
        const isPassword = passwordField.type === 'password';
        passwordField.type = isPassword ? 'text' : 'password';
        togglePassword.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });

        const toggleConfirmPassword = document.getElementById('toggle-confirm-password');
        const confirmPasswordField = document.getElementById('confirm-password');
        
        toggleConfirmPassword.addEventListener('click', (e) => {
        e.preventDefault();
        const isPassword = confirmPasswordField.type === 'password';
        confirmPasswordField.type = isPassword ? 'text' : 'password';
        toggleConfirmPassword.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });

        document.getElementById('login-link').addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = '../../index.html?openModal=true';
        });

        document.getElementById('registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const response = await fetch('../controllers/userController.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            window.location.href = result.redirect;
        } else {
            const error = document.getElementById('form-error');
            error.textContent = result.message;
            error.classList.remove('d-none');
        }
        });
    </script>
</body>
</html>
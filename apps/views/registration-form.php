<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Form | Dr. Aprille Ventura Clinica Dental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../public/css/styles.css">
</head>
<body class="vd-form-page d-flex align-items-center justify-content-center min-vh-100 py-5">

  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-8 col-lg-5">

        <div class="card vd-page-card border p-4 p-md-5">
          <form action="../controllers/users/addUsers.php" method="POST">

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

            <!-- TODO: add password strength indicator -->
            <!-- TODO: add show password toggle -->
            <div class="mb-3">
              <label for="password" class="vd-label form-label">Password</label>
              <input type="password" id="password" name="password" class="form-control vd-input" required>
            </div>

            <div class="mb-3">
              <label for="confirm-password" class="vd-label form-label">Confirm Password</label>
              <input type="password" id="confirm-password" name="confirm-password" class="form-control vd-input" required>
            </div>

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
  <script>
    document.getElementById('login-link').addEventListener('click', (e) => {
      e.preventDefault();
      window.location.href = '../../index.html?openModal=true';
    });
  </script>
</body>
</html>
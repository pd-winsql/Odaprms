<div class="p-4 p-md-5">
  <form action="apps/controllers/users/checkUsers.php" method="POST">
    <div class="text-center mb-4">
      <img src="public/assets/logo.png" alt="Logo" style="height:80px;">
    </div>

    <p class="text-uppercase text-center mb-4" style="font-size:10px; letter-spacing:0.22em; color:#b5924c; font-weight:500;">
      Login
      <span class="d-block mt-1" style="border-bottom:1px solid #d9c9a8;"></span>
    </p>

    <div class="mb-3">
      <label for="email" class="vd-label form-label">Email or Username</label>
      <input type="text" id="email" name="email" class="form-control vd-input" required>
    </div>

    <div class="mb-3">
      <label for="password" class="vd-label form-label">Password</label>
      <input type="password" id="password" name="password" class="form-control vd-input" required>
    </div>

    <button type="submit" class="btn vd-btn-gold w-100 mt-3">Login</button>

    <p class="text-center mt-3 small">
      Don't have an account?
      <a href="apps/views/registration-form.php" onclick="closeModal('myModal');" style="color:#b5924c;">Register</a>
    </p>
  </form>
</div>
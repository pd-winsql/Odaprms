<div class="page">
    <form action="registration-form.php" method="POST" class="login-form">
        <img src="public/assets/logo.png" alt="Logo" class="logo-log">
        <h2 class="section-title">Login</h2>

        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username email" name="username" required>
        </div>
        
        <div class="form-group"> 
        <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn-primary">Login</button>
        <div style="text-align:center; margin-top: 16px;">
            <span>Don't have an account?</span>
            <a href="apps/views/ventura_dental_form.php" onclick="closeModal('myModal');">Register</a>
        </div>
        </form>
</div>
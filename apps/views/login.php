
    <form action="login.php" method="POST" class="login-form">
        <img src="public/assets/logo.png" alt="Logo" class="logo">
        <h2>Login to Your Account</h2>
        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
        <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn-primary">Login</button>
        <div style="text-align:center; margin-top: 16px;">
        <span>Don't have an account? </span>
        <a href="#" onclick="closeModal('myModal'); openModal('registerModal'); return false;">Register</a>
        </div>
    </form>
<?php
session_start();
require_once "includes/db.php";

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['user_name']);
    $password = trim($_POST['user_pass']);

    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Admin Panel</title>
  <link rel="icon" href="assets/images/logo.png" type="image/x-icon" />
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-page">
  <div class="background"></div>

  <div class="login-container">
    <div class="login-left">
      <div class="brand">
        <img src="assets/images/logo.png" alt="Logo" class="logo"/>
        <h1>Admin Monitoring System</h1>
        <p>Secure Access Portal</p>
      </div>

      <form class="login-box" method="POST" action="" autocomplete="off">
        <div class="input-group">
          <input type="text" name="user_name" id="user_name" required autocomplete="off" />
          <label for="username">Username</label>
        </div>

         <div class="input-group">
    <input type="password" name="user_pass" id="user_pass" required autocomplete="new-password" minlength="8" />
    <label for="user_pass">Password</label>
    <span class="toggle-pass" onclick="togglePassword()">👁️</span>
  </div>

        <div class="options">
            <a href="forgot_password.php" class="forgot">Forgot password?</a>
        </div>

        <button type="submit" class="btn" name="login">Access System</button>

        <?php if ($error !== ''): ?>
          <p class="message" style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
      </form>
    </div>

    <div class="login-right">
      <img src="assets/images/company_picture.png" alt="Company Image" />
    </div>
  </div>

  <footer>
    <p>© <?php echo date("Y"); ?> PT. Victory Blessings Indonesia. All rights reserved.</p>
  </footer>

  <script>
    function togglePassword() {
  const passInput = document.getElementById('user_pass');
  passInput.type = passInput.type === 'password' ? 'text' : 'password';
}

  </script>
</body>
</html>

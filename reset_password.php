<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!isset($_SESSION['verified'])) {
    header("Location: forgot_password.php");
    exit;
}

$success = $error = '';
$formHidden = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $password = trim($_POST['password']);
    $hashed = hashPassword($password);
    $email = $_SESSION['reset_email'];

    $stmt = $conn->prepare("UPDATE admin SET password=? WHERE email=?");
    $stmt->bind_param("ss", $hashed, $email);

    if ($stmt->execute()) {
        session_unset();
        session_destroy();
        $success = "Password berhasil direset!";
        $formHidden = true; // Sembunyikan form
    } else {
        $error = "Gagal reset password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password - Monitoring System</title>
  <link rel="icon" href="assets/images/logo.png" type="image/x-icon" />
  <link rel="stylesheet" href="assets/css/login.css" />
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <div class="brand">
        <img src="assets/images/logo.png" alt="Company Logo" class="logo"/>
        <h1>Reset Password</h1>
        <p>Enter your new password below</p>
      </div>

      <?php if (!empty($success)): ?>
        <div class="login-box" style="text-align: center;">
    <p class="message" style="color: #28a745; font-size: 18px; margin-bottom: 20px;">
      Password has been successfully reset!
    </p>
    <a href="index.php" class="btn" style="text-decoration: none; display: inline-block;">Back to Login</a>
  </div>
<?php else: ?>
  <form class="login-box" method="POST" autocomplete="off">
    <div class="input-group">
      <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password"/>
      <label for="password">New Password</label>
      <span class="toggle-pass" onclick="togglePassword()">👁️</span>
    </div>

    <button type="submit" class="btn" name="reset">Reset Password</button>

    <?php if (!empty($error)): ?>
      <p class="message" style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
  </form>

  <div class="back-login">
    <a href="index.php">← Back to Login</a>
  </div>
<?php endif; ?>
</div>

    <div class="login-right">
      <img src="assets/images/company_picture.png" alt="Company Image">
    </div>
  </div>

  <footer>
    <p>© <?php echo date("Y"); ?> PT. Victory Blessings Indonesia. All rights reserved.</p>
  </footer>

  <script>
    function togglePassword() {
      const passInput = document.getElementById('password');
      passInput.type = passInput.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>

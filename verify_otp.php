<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

// Redirect jika belum melewati forgot_password
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $input_otp = trim($_POST['otp']);

    if (time() - $_SESSION['otp_time'] > 600) { // >10 menit
        $error = "OTP sudah kedaluwarsa!";
        session_unset();
        session_destroy();
    } elseif ($input_otp == $_SESSION['otp']) {
        $_SESSION['verified'] = true;
        header("Location: reset_password.php");
        exit;
    } else {
        $error = "OTP salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verify OTP - Monitoring System</title>
  <link rel="icon" href="assets/images/logo.png" type="image/x-icon" />
  <link rel="stylesheet" href="assets/css/login.css" />
</head>
<body>
  <div class="login-container">
    <div class="login-left">
      <div class="brand">
        <img src="assets/images/logo.png" alt="Company Logo" class="logo"/>
        <h1>OTP Verification</h1>
        <p>Enter the 6-digit code sent to your email</p>
      </div>

      <form method="POST" class="login-box" autocomplete="off">
        <div class="input-group">
          <input type="text" name="otp" id="otp" required pattern="\d{6}" maxlength="6" />
          <label for="otp">OTP</label>
        </div>

        <button type="submit" class="btn" name="verify">Verify</button>

        <?php if (!empty($error)): ?>
          <p class="message" style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
      </form>

      <div class="back-login">
        <a href="forgot_password.php">← Back to Forgot Password</a>
      </div>
    </div>

    <div class="login-right">
      <img src="assets/images/company_picture.png" alt="Company Image" />
    </div>
  </div>

  <footer>
    <p>© <?php echo date("Y"); ?> PT. Victory Blessings Indonesia. All rights reserved.</p>
  </footer>
</body>
</html>

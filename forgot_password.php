<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $otp = rand(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_time'] = time();

            $subject = "OTP Password Reset";
            $msg = "Hello,<br>Your OTP to reset the password is: <b>$otp</b>. It is valid for 10 minutes.";

            if (sendEmail($email, $subject, $msg)) {
                $success = true;
                $message = "OTP has been sent to your email. Please check your inbox.";
                // Optional redirect:
                header("Location: verify_otp.php");
                exit;
            } else {
                $message = "Failed to send email. Please check PHPMailer configuration.";
            }
        } else {
            $message = "Email not found in the system.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password - Monitoring System</title>
  <link rel="icon" href="assets/images/logo.png" type="image/x-icon" />
  <link rel="stylesheet" href="assets/css/login.css" />
</head>
<body>
  <div class="login-container">
    <!-- Forgot Password Form -->
    <div class="login-left">
      <div class="brand">
        <img src="assets/images/logo.png" alt="Company Logo" class="logo"/>
        <h1>Password Recovery</h1>
        <p>Enter your email to reset your password</p>
      </div>

      <form method="POST" class="login-box" autocomplete="off">
        <div class="input-group">
          <input type="email" id="email" name="email" required />
          <label for="email">Email</label>
        </div>

        <button type="submit" class="btn" name="send_otp">Send Reset OTP</button>
        <?php if (!empty($message)): ?>
          <p class="message" style="color: <?php echo $success ? 'green' : 'red'; ?>;">
            <?php echo $message; ?>
          </p>
        <?php endif; ?>
      </form>

      <div class="back-login">
        <a href="index.php">← Back to Login</a>
      </div>
    </div>

    <!-- Right Image -->
    <div class="login-right">
      <img src="assets/images/company_picture.png" alt="Company Image">
    </div>
  </div>

  <footer>
    <p>© <?php echo date("Y"); ?> PT. Victory Blessings Indonesia. All rights reserved.</p>
  </footer>
</body>
</html>

<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Logging Out...</title>
  <link rel="icon" href="assets/images/logo.png" type="image/x-icon" />
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f6f9fc;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      text-align: center;
      color: #333;
    }

    .logout-box {
      background: white;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      animation: fadeIn 0.8s ease;
    }

    .logout-box img {
      width: 80px;
      margin-bottom: 20px;
    }

    .logout-box h2 {
      margin: 0;
      font-size: 24px;
      color: #4CAF50;
    }

    .logout-box p {
      margin-top: 10px;
      font-size: 16px;
    }

    .manual-button {
      margin-top: 25px;
    }

    button {
      padding: 12px 28px;
      font-size: 16px;
      background: linear-gradient(135deg, #4CAF50, #2e7d32);
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    button:hover {
      background: linear-gradient(135deg, #43a047, #1b5e20);
      box-shadow: 0 6px 18px rgba(76, 175, 80, 0.4);
      transform: translateY(-2px);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.95); }
      to { opacity: 1; transform: scale(1); }
    }

    .button-icon {
      font-size: 18px;
    }
  </style>
  <script>
    let seconds = 5;
    const countdownEl = () => document.getElementById('countdown');

    const updateCountdown = () => {
      if (seconds > 0) {
        countdownEl().textContent = seconds;
        seconds--;
      } else {
        window.location.href = "index.php";
      }
    };

    window.onload = () => {
      updateCountdown();
      setInterval(updateCountdown, 1000);
    };

    function manualRedirect() {
      window.location.href = "index.php";
    }
  </script>
</head>
<body>
  <div class="logout-box">
    <img src="assets/images/logo.png" alt="Logo">
    <h2>You have been logged out</h2>
    <p>Redirecting to login page in <span id="countdown">5</span> seconds...</p>
    <div class="manual-button">
      <p>or click the button below:</p>
      <button onclick="manualRedirect()">
        <span class="button-icon"></span> Back to Login
      </button>
    </div>
  </div>
</body>
</html>

<?php
session_start();
include "config.php"; // loads database connection ($pdo or mysqli)

// Check if session has user_id
$redirect = isset($_SESSION['user_id']) ? "dashboard.php" : "introduction.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OPay Clone</title>
<style>body {
      margin: 0;
      height: 100vh;
      background-color: #00B876; /* Green background */
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      font-family: Arial, sans-serif;
      color: #1E2A78; /* Dark blue text */
      text-align: center;
    }
    
    .logo-bg {
        background-color: white;
        border-radius: 100%;
        width: auto;
        height: auto;
        place-items: center;
        justify-content: center;
        overflow: hidden;
        display: flex;
        padding: 3px;
        margin-bottom: 10px;
    }

    .logo {
      width: 65px;
      height: 65px;
    }

    h1 {
      font-size: 24px;
      font-weight: 900;
      margin: 0;
      margin-bottom: 15px;
    }

    .footer {
      margin-top: 20px;
      width: 250px;
    }
    </style>
  <script>
    // Auto redirect after 5 seconds
    setTimeout(function() {
      window.location.href = "<?php echo $redirect; ?>";
    }, 5000);
  </script>
</head>
<body>
  <!-- Logo Image -->
  <div class="logo-bg">
    <img src="images/dashboard/logo.png" alt="Logo" class="logo">
  </div>

  <!-- Tagline -->
  <h1>We are Beyond Banking</h1>

  <!-- License Image -->
  <img src="images/dashboard/bottom.png" alt="Licensed by CBN and NDIC" class="footer" style="max-height: 100vh; max-width: 100vh; width: 100%;">
</body>
</html>
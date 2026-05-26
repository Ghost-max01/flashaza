<?php
session_start();
include "config.php"; // contains $pdo (PDO connection)

$error = "";

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? "");
    $password = trim($_POST['password'] ?? "");

    if ($username === "" || $password === "") {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Detect if username is email or phone number
            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                // Email login
                $stmt = $pdo->prepare("SELECT uid, password FROM users WHERE email = :value LIMIT 1");
            } else {
                // Phone number login
                $stmt = $pdo->prepare("SELECT uid, password FROM users WHERE number = :value LIMIT 1");
            }

            $stmt->execute(['value' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Success
                $_SESSION['user_id'] = $user['uid'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (Exception $e) {
            $error = "Something went wrong. Try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
  <title>Opay Login</title>
  <link rel="stylesheet" href="css/login.css">
</head>
<body>
  <!-- Return icon -->
  <span class="material-icons return-icon" id="returnIcon" onclick="window.history.back()">chevron_left</span>

  <!-- QR Code -->
  <div class="qr-container">
    <img src="images/dashboard/qr_opay.png" alt="OPay QR" style="height: 35px;">
  </div>

  <!-- Login Form -->
  <form class="login-form" id="loginForm" method="POST" action="">
    <!-- Mobile/Email Input -->
    <div class="input-container input-group">
      <input type="text" class="input-field <?php echo $error ? 'error' : ''; ?>" id="username" name="username" placeholder="Enter your Mobile No./Email" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
    </div>

    <!-- Password Input -->
    <div class="password-container">
      <input type="password" class="password-field <?php echo $error ? 'error' : ''; ?>" id="password" name="password" placeholder="Enter 6-digit Password" inputmode="numeric" maxlength="6" required>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
      <div class="error-text" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
      <div class="error-text" id="errorText">Please enter valid credentials</div>
    <?php endif; ?>

    <!-- Forgot Password -->
    <div class="forgot-password" onclick="window.location.href='forget.php'">Forgot Password?</div>

    <!-- Login Button -->
    <button type="submit" class="login-button" id="loginButton">
      <span>Log in</span>
    </button>

    <!-- Progress Spinner -->
    <div class="progress-container" id="progressContainer">
      <div class="progress-bar"></div>
    </div>
  </form>

  <!-- Spacer -->
  <div class="spacer"></div>

  <!-- Sign Up Section -->
  <div class="signup-container">
    <div class="signup-text">Don't have an Opay Account yet?</div>
    <a href="signup.php" class="signup-link">Click here to get one</a>
  </div>

  <!-- Footer -->
  <div class="footer-image">
    <img src="images/dashboard/footer.png" alt="Footer Image">
  </div>
</body>
</html>
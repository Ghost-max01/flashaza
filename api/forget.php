<?php
session_start();
require_once 'config.php'; // PDO database connection
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send reset code via email
function sendResetCode($email, $code) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'mail@web404space.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clone@web404space.com';
        $mail->Password   = 'Maxwell198$';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('clone@web404space.com', 'OPay Clone');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Code';

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OPay Clone - Reset Password Code</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
<link rel="stylesheet" href="css/forget.css">
  </style>
</head>
<body>
  <div class="card">
    <h2>OPay Clone</h2>
    <p>Your Reset Password Code is:</p>
    <div class="code">$code</div>
    <p>Use this code to reset your password securely.</p>
  </div>
</body>
</html>
HTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'request_reset') {
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                exit;
            }
            
            // Check if user exists in database
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Generate 4-digit code
                    $reset_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store code in session (you might want to use database instead)
                    $_SESSION['reset_code'] = $reset_code;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_expires'] = $expires;
                    
                    // Send email
                    if (sendResetCode($email, $reset_code)) {
                        echo json_encode(['success' => true, 'message' => 'Reset code sent to your email']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Email not found in our system']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
        elseif ($action === 'verify_code') {
            $code = trim($_POST['code']);
            
            if (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_expires'])) {
                echo json_encode(['success' => false, 'message' => 'No reset request found']);
                exit;
            }
            
            if (time() > strtotime($_SESSION['reset_expires'])) {
                echo json_encode(['success' => false, 'message' => 'Reset code has expired']);
                exit;
            }
            
            if ($code === $_SESSION['reset_code']) {
                $_SESSION['code_verified'] = true;
                echo json_encode(['success' => true, 'message' => 'Code verified successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
            }
        }
        elseif ($action === 'reset_password') {
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);
            
            if (!isset($_SESSION['code_verified']) || !$_SESSION['code_verified']) {
                echo json_encode(['success' => false, 'message' => 'Code not verified']);
                exit;
            }
            
            if (strlen($password) !== 6 || !is_numeric($password)) {
                echo json_encode(['success' => false, 'message' => 'Password must be 6 digits']);
                exit;
            }
            
            if ($password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
                exit;
            }
            
            // Update password in database
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                $stmt->execute([
                    'password' => $hashed_password,
                    'email' => $_SESSION['reset_email']
                ]);
                
                // Clear reset session
                unset($_SESSION['reset_code']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_expires']);
                unset($_SESSION['code_verified']);
                
                echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - OPay Clone</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/forget.css">
</head>
<body>
    <div class="container">
        <!-- Theme Toggle -->
        <div class="theme-toggle" id="theme-toggle">
            <span class="material-icons" id="theme-icon">dark_mode</span>
        </div>
        
        <!-- Step 1: Email Input -->
        <div class="step active" id="step1">
            <div class="back-button" onclick="history.back()">
                <span class="material-icons">chevron_left</span>
                Back
            </div>
            
            <h2 class="title">Reset Your Password</h2>
            
            <div class="message" id="message1"></div>
            
            <div class="input-container">
                <input type="email" class="input-field" placeholder="Enter Your Email To Reset" id="emailInput">
            </div>
            
            <div class="btn" id="continueBtn">Continue</div>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress1"></div>
                </div>
                <div class="step-indicator">Step 1 of 3</div>
            </div>
        </div>
        
        <!-- Step 2: OTP Input -->
        <div class="step" id="step2">
            <div class="back-button" onclick="showStep(1)">
                <span class="material-icons">chevron_left</span>
                Back
            </div>
            
            <h2 class="title">Enter Reset Password Code</h2>
            
            <div class="message" id="message2"></div>
            
            <div class="input-container">
                <input type="text" class="input-field otp-input" placeholder="* * * *" maxlength="4" id="otpInput">
            </div>
            
            <div class="btn" id="verifyOtpBtn">Verify Code</div>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress2"></div>
                </div>
                <div class="step-indicator">Step 2 of 3</div>
            </div>
        </div>
        
        <!-- Step 3: Password Reset -->
        <div class="step" id="step3">
            <div class="back-button" onclick="showStep(2)">
                <span class="material-icons">chevron_left</span>
                Back
            </div>
            
            <h2 class="title">Create New Password</h2>
            
            <div class="message" id="message3"></div>
            
            <div class="input-container">
                <input type="password" class="input-field" placeholder="Enter 6 Digit Password" maxlength="6" id="newPassword">
            </div>
            
            <div class="input-container">
                <input type="password" class="input-field" placeholder="Confirm Password" maxlength="6" id="confirmPassword">
            </div>
            
            <div class="btn" id="confirmBtn">Confirm</div>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress3"></div>
                </div>
                <div class="step-indicator">Step 3 of 3</div>
            </div>
        </div>
    </div>

    <script>
            // Theme management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Set initial theme based on saved preference or system preference
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.body.classList.add('dark-mode');
                document.getElementById('theme-icon').textContent = 'light_mode';
            } else {
                document.body.classList.remove('dark-mode');
                document.getElementById('theme-icon').textContent = 'dark_mode';
            }
            
            // Toggle theme when button is clicked
            document.getElementById('theme-toggle').addEventListener('click', () => {
                if (document.body.classList.contains('dark-mode')) {
                    document.body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                    document.getElementById('theme-icon').textContent = 'dark_mode';
                } else {
                    document.body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                    document.getElementById('theme-icon').textContent = 'light_mode';
                }
            });
        }
        
        // Initialize theme
        initTheme();

        // Step management
        function showStep(stepNumber) {
            // Hide all steps
            document.querySelectorAll('.step').forEach(step => {
                step.classList.remove('active');
            });
            
            // Show the selected step
            document.getElementById(`step${stepNumber}`).classList.add('active');
            
            // Update progress bars
            document.getElementById('progress1').style.width = stepNumber >= 1 ? '33%' : '0%';
            document.getElementById('progress2').style.width = stepNumber >= 2 ? '66%' : '0%';
            document.getElementById('progress3').style.width = stepNumber >= 3 ? '100%' : '0%';
            
            // Clear messages
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.display = 'none';
                msg.textContent = '';
            });
        }
        
        // Show message
        function showMessage(step, message, isError = true) {
            const messageEl = document.getElementById(`message${step}`);
            messageEl.textContent = message;
            messageEl.className = isError ? 'message error' : 'message success';
            messageEl.style.display = 'block';
        }
        
        // Set up button event listeners
        document.getElementById('continueBtn').addEventListener('click', function() {
            const email = document.getElementById('emailInput').value;
            if (email && isValidEmail(email)) {
                this.disabled = true;
                this.textContent = 'Sending...';
                
                // Send request to server
                const formData = new FormData();
                formData.append('action', 'request_reset');
                formData.append('email', email);
                
                fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(1, data.message, false);
                        setTimeout(() => showStep(2), 1500);
                    } else {
                        showMessage(1, data.message);
                    }
                    this.disabled = false;
                    this.textContent = 'Continue';
                })
                .catch(error => {
                    showMessage(1, 'Network error. Please try again.');
                    this.disabled = false;
                    this.textContent = 'Continue';
                });
            } else {
                showMessage(1, 'Please enter a valid email address');
            }
        });
        
        document.getElementById('verifyOtpBtn').addEventListener('click', function() {
            const code = document.getElementById('otpInput').value;
            if (code.length === 4) {
                this.disabled = true;
                this.textContent = 'Verifying...';
                
                // Send request to server
                const formData = new FormData();
                formData.append('action', 'verify_code');
                formData.append('code', code);
                
                fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(2, data.message, false);
                        setTimeout(() => showStep(3), 1500);
                    } else {
                        showMessage(2, data.message);
                    }
                    this.disabled = false;
                    this.textContent = 'Verify Code';
                })
                .catch(error => {
                    showMessage(2, 'Network error. Please try again.');
                    this.disabled = false;
                    this.textContent = 'Verify Code';
                });
            } else {
                showMessage(2, 'Please enter a valid 4-digit code');
            }
        });
        
        document.getElementById('confirmBtn').addEventListener('click', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword.length !== 6 || !/^\d+$/.test(newPassword)) {
                showMessage(3, 'Password must be exactly 6 digits');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage(3, 'Passwords do not match');
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Resetting...';
            
            // Send request to server
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(3, data.message, false);
                    // Redirect to login page after 2 seconds
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showMessage(3, data.message);
                    this.disabled = false;
                    this.textContent = 'Confirm';
                }
            })
            .catch(error => {
                showMessage(3, 'Network error. Please try again.');
                this.disabled = false;
                this.textContent = 'Confirm';
            });
        });
        
        // Email validation function
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Initialize progress bars
        showStep(1);
    </script>
</body>
</html>
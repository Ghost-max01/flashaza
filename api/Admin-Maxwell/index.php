<?php
session_start();
require_once 'config.php'; // DB connection must define $pdo

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ? AND password = ?");
        $stmt->execute([$email, $password]);
        $admin = $stmt->fetch();

        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_name'] = $admin['full_name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Secure Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #6C37F2;
            --primary-light: #8C65F5;
            --primary-dark: #5A2BD9;
            --white: #FFFFFF;
            --light-gray: #F5F7FA;
            --medium-gray: #E4E7EB;
            --text: #2D3748;
            --text-light: #718096;
            --error: #E53E3E;
            --success: #38A169;
        }

        body {
            background: linear-gradient(135deg, var(--light-gray) 0%, var(--white) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            height: 550px;
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(108, 55, 242, 0.2);
            animation: fadeIn 0.8s ease-out;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .login-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -30px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .brand {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            z-index: 1;
        }

        .brand i {
            font-size: 36px;
            margin-right: 12px;
        }

        .brand h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .welcome-text {
            margin-bottom: 30px;
            z-index: 1;
        }

        .welcome-text h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .welcome-text p {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
        }

        .features {
            z-index: 1;
        }

        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .feature i {
            font-size: 18px;
            background: rgba(255, 255, 255, 0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .feature p {
            font-size: 15px;
        }

        .login-right {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header h2 {
            color: var(--text);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 500;
            font-size: 14px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid var(--medium-gray);
            border-radius: 12px;
            font-size: 16px;
            color: var(--text);
            transition: all 0.3s ease;
            background: var(--light-gray);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 55, 242, 0.2);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input {
            margin-right: 8px;
            accent-color: var(--primary);
        }

        .remember-me label {
            color: var(--text-light);
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 55, 242, 0.3);
        }

        .login-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 55, 242, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #FEE2E2;
            color: var(--error);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            display: <?php echo $loginError ? 'block' : 'none'; ?>;
        }

        .error-message i {
            margin-right: 8px;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: var(--text-light);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--medium-gray);
        }

        .divider span {
            padding: 0 15px;
            font-size: 14px;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-gray);
            color: var(--text-light);
            border: 1px solid var(--medium-gray);
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            background: var(--white);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transform: translateY(-3px);
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: var(--text-light);
            font-size: 14px;
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
            100% {
                transform: translateY(0);
            }
        }

        .floating {
            animation: float 4s ease-in-out infinite;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                height: auto;
            }
            
            .login-left {
                padding: 30px;
            }
            
            .login-right {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="brand">
                <i class="fas fa-lock floating"></i>
                <h1>AdminPortal</h1>
            </div>
            
            <div class="welcome-text">
                <h2>Secure Access to Your Dashboard</h2>
                <p>Sign in to manage your platform and access exclusive features designed for administrators.</p>
            </div>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <p>Enterprise-grade security with 2FA support</p>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <p>Comprehensive analytics and reporting</p>
                </div>
                <div class="feature">
                    <i class="fas fa-cog"></i>
                    <p>Full system configuration controls</p>
                </div>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to continue to your admin dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="admin@example.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="login-btn">Sign In</button>
                
                <div class="divider">
                    <span>Or continue with</span>
                </div>
                
                <div class="social-login">
                    <button type="button" class="social-btn">
                        <i class="fab fa-google"></i>
                    </button>
                    <button type="button" class="social-btn">
                        <i class="fab fa-microsoft"></i>
                    </button>
                    <button type="button" class="social-btn">
                        <i class="fab fa-github"></i>
                    </button>
                </div>
                
                <p class="footer-text">Need help? <a href="#">Contact support</a></p>
            </form>
        </div>
    </div>
    
    <script>
        // Form validation and animations
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            // Show loading state
            const loginBtn = document.querySelector('.login-btn');
            loginBtn.textContent = 'Authenticating...';
            loginBtn.disabled = true;
            
            return true;
        });
        
        // Add floating animation to lock icon
        const lockIcon = document.querySelector('.fa-lock.floating');
        setInterval(() => {
            lockIcon.classList.toggle('floating');
        }, 4000);
    </script>
</body>
</html>
<?php
/**
 * signup.php
 * - Verifies OPay account name via your custom API (https://webtech.net.ng/vrf/verify.php)
 * - Reveals email+password after verification
 * - Registers user to `users` table and logs them in (session user_id = uid)
 */
session_start();
include "config.php"; // must define $pdo (PDO)

header_remove('X-Powered-By');

// ---------- Helpers ----------
function json_response($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function random_uid($length = 20) {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function get_client_ip() {
    foreach ([
        'HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP','HTTP_FORWARDED_FOR','HTTP_FORWARDED','REMOTE_ADDR'
    ] as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            return trim($ipList[0]);
        }
    }
    return '0.0.0.0';
}

function get_device_name() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
}

// ðŸ”¹ Your gateway for OPay verification
function resolve_opay_account($account_number, $bank_code = '100004') {
    $url = 'https://webtech.net.ng/vrf/verify.php';
    $post = http_build_query([
        'account_number' => $account_number,
        'bank_code'      => $bank_code
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'message' => 'Network error: ' . $err];
    }

    $res = trim($res);

    // If API returns "Error ..." treat as invalid account
    if (stripos($res, 'error') !== false) {
        return ['ok' => false, 'message' => 'Incorrect OPay account number'];
    }

    // Otherwise treat raw response as the account name
    return [
        'ok'            => true,
        'account_name'  => $res,
        'account_number'=> $account_number,
        'bank_name'     => 'OPay'
    ];
}

// ---------- AJAX endpoints ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'verify_phone') {
        $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
        if (strlen($phone) !== 10) {
            json_response(['ok' => false, 'message' => 'Phone must be exactly 10 digits'], 400);
        }

        // Optionally check if already registered
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE number = :num LIMIT 1");
        $stmt->execute(['num' => $phone]);
        if ($stmt->fetchColumn()) {
            json_response(['ok' => false, 'message' => 'This phone number is already registered. Please log in.'], 409);
        }

        // Resolve with your gateway
        $result = resolve_opay_account($phone, '100004');
        if ($result['ok'] !== true) {
            json_response(['ok' => false, 'message' => $result['message'] ?? 'Verification failed'], 400);
        }

        // Save verified phone + name in session to tie the next step
        $_SESSION['verified_phone'] = $phone;
        $_SESSION['verified_name']  = $result['account_name'];

        json_response([
            'ok' => true,
            'name' => $result['account_name'],
            'bank' => $result['bank_name'],
            'number' => $phone
        ]);
    }

    if ($action === 'register') {
        // Pull session-verified phone
        $verified_phone = $_SESSION['verified_phone'] ?? null;
        $verified_name  = $_SESSION['verified_name'] ?? null;

        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$verified_phone || !$verified_name) {
            json_response(['ok' => false, 'message' => 'Please verify your OPay number first.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['ok' => false, 'message' => 'Invalid email address.'], 400);
        }
        if (!preg_match('/^\d{6}$/', $password)) {
            json_response(['ok' => false, 'message' => 'Password must be 6 digits.'], 400);
        }

        // Check duplicates
        $q = $pdo->prepare("SELECT 1 FROM users WHERE email = :email OR number = :number LIMIT 1");
        $q->execute(['email' => $email, 'number' => $verified_phone]);
        if ($q->fetchColumn()) {
            json_response(['ok' => false, 'message' => 'Email or phone already registered.'], 409);
        }

        // Re-resolve (defense-in-depth)
        $recheck = resolve_opay_account($verified_phone, '100004');
        if ($recheck['ok'] !== true) {
            json_response(['ok' => false, 'message' => 'Unable to re-verify OPay account. Try again.'], 400);
        }
        $resolved_name = $recheck['account_name'];

        // Build insert payload
        $uid = random_uid(20);
        $now = date('Y-m-d H:i:s');

        $device = get_device_name();      // user agent string
        $android_id = get_client_ip();    // treat as "android_id"

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert
        $sql = "INSERT INTO users
            (uid, name, number, device, email, date, password, profile, android_id, plan, subscription_date, amount_in, amount_out, email_alert, block, balance)
            VALUES
            (:uid, :name, :number, :device, :email, :date, :password, :profile, :android_id, :plan, :subscription_date, :amount_in, :amount_out, :email_alert, :block, :balance)";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            'uid'               => $uid,
            'name'              => $resolved_name,
            'number'            => $verified_phone,
            'device'            => $device,
            'email'             => $email,
            'date'              => $now,
            'password'          => $hash,
            'profile'           => '',
            'android_id'        => $android_id,
            'plan'              => 'free',
            'subscription_date' => 0,
            'amount_in'         => '0.00',
            'amount_out'        => '0.00',
            'email_alert'       => 0,
            'block'             => 0,
            'balance'           => '35000'
        ]);

        if (!$ok) {
            json_response(['ok' => false, 'message' => 'Could not create account.'], 500);
        }

        // Login (session) and redirect
        $_SESSION['user_id'] = $uid;

        // cleanup phone verify session
        unset($_SESSION['verified_phone'], $_SESSION['verified_name']);

        json_response(['ok' => true, 'redirect' => 'dashboard.php']);
    }

    // Unknown action
    json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
}

// ---------- Render page (GET) ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPay - Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
          :root {
            --bg-color: #FFFFFF;
            --text-color: #212121;
            --input-bg: #FFFFFF;
            --input-border: #E0E0E0;
            --input-text: #000000;
            --input-placeholder: #BDBDBD;
            --input-hint-bg: #FFFFFF;
            --input-hint-text: #757575;
            --title-color: #000000;
            --button-bg: #00B678;
            --button-text: #FFFFFF;
            --name-display-color: #003c2f;
            --prompt-text: #424242;
            --login-link: #00B876;
            --validation-error: #F44336;
            --footer-bg: transparent;
        }

        .dark-mode {
            --bg-color: #121212;
            --text-color: #E0E0E0;
            --input-bg: #1E1E1E;
            --input-border: #424242;
            --input-text: #E0E0E0;
            --input-placeholder: #757575;
            --input-hint-bg: #1E1E1E;
            --input-hint-text: #BDBDBD;
            --title-color: #FFFFFF;
            --button-bg: #00B678;
            --button-text: #FFFFFF;
            --name-display-color: #4DB6AC;
            --prompt-text: #E0E0E0;
            --login-link: #4DB6AC;
            --validation-error: #CF6679;
            --footer-bg: #1E1E1E;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter', sans-serif; }
        body { 
            background-color: var(--bg-color); 
            color: var(--text-color); 
            width:100%; 
            min-height:100vh; 
            position:relative; 
            overflow-x:hidden; 
            transition: background-color 0.3s, color 0.3s;
        }
        .container { display:flex; flex-direction:column; width:100%; min-height:100vh; max-width:100%; margin:0 auto; }
        .header { 
            padding:1.5vw; 
            width:100%; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .back-button { width:6vw; height:6vw; max-width:24px; max-height:24px; display:flex; justify-content:center; align-items:center; cursor:pointer; }
        .back-button img { width:100%; height:100%; object-fit:contain; }

        .theme-toggle {
            width: 6vw;
            height: 6vw;
            max-width: 24px;
            max-height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            color: var(--text-color);
        }

        .content { flex:1; display:flex; flex-direction:column; align-items:center; padding:2vw; background-color:var(--bg-color); width:100%; }

        .qr-code { width:35vw; height:35vw; max-width:150px; display:flex; justify-content:center; align-items:center; }
        .qr-code img { height:35px; }

        .title { width:100%; margin-top:8vw; padding:2vw; text-align:center; font-size:6.5vw; font-weight:bold; color:var(--title-color); }
        @media (min-width:480px){ .title{ font-size:28px; margin-top:30px; } }

        .form-container { width:100%; display:flex; flex-direction:column; align-items:center; }

        .input-container { 
            width:90%; 
            margin:2.5vw 0; 
            border:1px solid var(--input-border); 
            border-radius:2vw; 
            padding:1.5vw 4vw; 
            background-color:var(--input-bg); 
            position:relative; 
            transition: border-color 0.3s, background-color 0.3s;
        }
        .input-container.invalid { border-color:var(--validation-error); }

        .input-group { display:flex; align-items:center; height:12vw; min-height:50px; }
        .input-icon { width:10vw; height:10vw; max-width:40px; max-height:40px; display:flex; justify-content:center; align-items:center; margin-right:2vw; }
        .input-icon img { max-width:100%; max-height:100%; object-fit:contain; }

        .country-code { padding:2vw; font-size:4vw; color:var(--input-text); font-weight:500; }
        @media (min-width:480px){ .country-code{ font-size:16px; } }

        .divider { padding:0 2vw; font-size:5vw; color:var(--input-border); }
        @media (min-width:480px){ .divider{ font-size:20px; } }

        .form-input { 
            flex:1; 
            padding:2vw; 
            background-color:transparent; 
            border:none; 
            outline:none; 
            font-size:4vw; 
            color:var(--input-text); 
            height:100%; 
            width:100%; 
            transition: color 0.3s;
        }
        @media (min-width:480px){ .form-input{ font-size:16px; } }
        .form-input::placeholder { color:var(--input-placeholder); }

        .input-hint { 
            position:absolute; 
            bottom:-18px; 
            right:10px; 
            font-size:3vw; 
            color:var(--input-hint-text); 
            background-color:var(--input-hint-bg); 
            padding:0 5px; 
            transition: color 0.3s, background-color 0.3s;
        }
        @media (min-width:480px){ .input-hint{ font-size:12px; } }

        /* Read-only name display (after verification) */
        .name-display { width:90%; margin-top:8px; text-align:center; font-size:14px; color:var(--name-display-color); display:none; }

        /* Sign Up Button */
        .signup-button { 
            width:85%; 
            height:12vw; 
            min-height:50px; 
            margin:6vw 0; 
            background-color:var(--button-bg); 
            opacity:0.5; 
            border-radius:2vw; 
            display:flex; 
            justify-content:center; 
            align-items:center; 
            cursor:pointer; 
            transition:opacity 0.3s, background-color 0.3s;
        }
        .signup-button.active { opacity:1; }
        .signup-button-text { color:var(--button-text); font-size:4.5vw; font-weight:bold; }
        @media (min-width:480px){ .signup-button-text{ font-size:18px; } }

        /* Progress Bar */
        .progress-container { width:100%; display:flex; justify-content:center; margin:-15vw 0 5vw 0; }
        .progress-bar { width:17vw; height:17vw; max-width:70px; max-height:70px; padding:2vw; display:none; }
        .progress-bar img { width:100%; height:100%; object-fit:contain; }

        /* Footer */
        .footer { 
            width:100%; 
            padding:2vw; 
            display:flex; 
            flex-direction:column; 
            align-items:center; 
            margin-top:5vw; 
            background-color: var(--footer-bg);
            transition: background-color 0.3s;
        }
        .login-prompt { display:flex; justify-content:center; align-items:center; padding:2vw; flex-wrap:wrap; }
        .prompt-text { font-size:3.5vw; font-weight:bold; color:var(--prompt-text); padding:2vw; transition: color 0.3s; }
        @media (min-width:480px){ .prompt-text{ font-size:14px; } }
        .login-link { font-size:3.5vw; color:var(--login-link); padding:2vw; cursor:pointer; font-weight:500; transition: color 0.3s; }
        @media (min-width:480px){ .login-link{ font-size:14px; } }
        .footer-image { display:flex; justify-content:center; align-items:center; padding:4vw 0; margin-top:5vw; width:100%; }
        .footer-image img { max-width:80%; max-height:25vw; object-fit:contain; }

        .validation-message { color:var(--validation-error); font-size:3.5vw; margin-top:1vw; text-align:center; display:none; transition: color 0.3s; }
        @media (min-width:480px){ .validation-message{ font-size:14px; } }

        /* Hide email and password until phone verified */
        #email-container, #password-container, #signup-btn { display:none; }
       </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="back-button" onclick="history.back()">
                <span class="material-icons return-icon" id="returnIcon">chevron_left</span>
            </div>
            <div class="theme-toggle" id="theme-toggle">
                <span class="material-icons" id="theme-icon">dark_mode</span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="qr-code">
                <img src="images/dashboard/qr_opay.png" alt="OPay QR">
            </div>

            <div class="title">Get an OPay Account</div>

            <form class="form-container" id="signup-form" onsubmit="return false;">
                <!-- Phone -->
                <div class="input-container" id="phone-container">
                    <div class="input-group">
                        <div class="input-icon"><img src="images/dashboard/nig.png" alt="Nigeria"></div>
                        <div class="country-code">+234</div>
                        <div class="divider">|</div>
                        <input type="tel" class="form-input" id="phone" name="phone" placeholder="Enter Phone Number" pattern="[0-9]{10}" inputmode="numeric" maxlength="10" required>
                    </div>
                    <div class="input-hint">10 digits</div>
                </div>

                <!-- Name (read-only, from bank verification) -->
                <div class="name-display" id="name-display"></div>

                <!-- Email -->
                <div class="input-container" id="email-container">
                    <div class="input-group">
                        <input type="email" class="form-input" id="email" name="email" placeholder="Enter Your Email">
                    </div>
                </div>

                <!-- Password -->
                <div class="input-container" id="password-container">
                    <div class="input-group">
                        <input type="password" class="form-input" id="password" name="password" placeholder="Enter Password (6 digits)" pattern="[0-9]{6}" inputmode="numeric" maxlength="6">
                    </div>
                    <div class="input-hint">6 digits</div>
                </div>

                <!-- Validate/Errors -->
                <div class="validation-message" id="validation-message"></div>

                <!-- Sign Up -->
                <div class="signup-button" id="signup-btn">
                    <div class="signup-button-text">Sign Up</div>
                </div>

                <!-- Progress -->
                <div class="progress-container">
                    <div class="progress-bar" id="progress-bar">
                        <img src="https://placehold.co/70x70/00B875/FFFFFF?text=Loading" alt="Loading">
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="login-prompt">
                <div class="prompt-text">Already Have an OPay Account?</div>
                <div class="login-link" id="login-link">Log in</div>
            </div>
            <div class="footer-image">
                <img src="images/dashboard/footer.png" alt="Footer Image">
            </div>
        </div>
    </div>

<script>
        (function(){
            const phoneInput = document.getElementById('phone');
            const emailContainer = document.getElementById('email-container');
            const passwordContainer = document.getElementById('password-container');
            const signupBtn = document.getElementById('signup-btn');
            const progressBar = document.getElementById('progress-bar');
            const validationMessage = document.getElementById('validation-message');
            const phoneContainer = document.getElementById('phone-container');
            const nameDisplay = document.getElementById('name-display');
            const loginLink = document.getElementById('login-link');
            const themeToggle = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            
            // Dark mode functionality
            function initDarkMode() {
                const savedTheme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                
                // Set initial theme based on saved preference or system preference
                if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                    document.body.classList.add('dark-mode');
                    themeIcon.textContent = 'light_mode';
                } else {
                    document.body.classList.remove('dark-mode');
                    themeIcon.textContent = 'dark_mode';
                }
                
                // Toggle theme when button is clicked
                themeToggle.addEventListener('click', () => {
                    if (document.body.classList.contains('dark-mode')) {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('theme', 'light');
                        themeIcon.textContent = 'dark_mode';
                    } else {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('theme', 'dark');
                        themeIcon.textContent = 'light_mode';
                    }
                });
            }
            
            // Initialize dark mode
            initDarkMode();

            // Allow only digits in phone
            phoneInput.addEventListener('keydown', function(e){
                if ([46,8,9,27,13].includes(e.keyCode) ||
                    (e.keyCode === 65 && e.ctrlKey) ||
                    (e.keyCode === 67 && e.ctrlKey) ||
                    (e.keyCode === 86 && e.ctrlKey) ||
                    (e.keyCode === 88 && e.ctrlKey) ||
                    (e.keyCode >= 35 && e.keyCode <= 39)) return;

                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) &&
                    (e.keyCode < 96 || e.keyCode > 105)) e.preventDefault();
            });

            // When phone reaches 10 digits, verify with backend (Flutterwave -> OPay)
            phoneInput.addEventListener('input', function(){
                validationMessage.style.display = 'none';
                phoneContainer.classList.remove('invalid');

                const v = phoneInput.value.replace(/\D+/g, '');
                if (v.length === 10) {
                    // Call verify
                    progressBar.style.display = 'block';
                    fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ action: 'verify_phone', phone: v })
                    })
                    .then(r => r.json())
                    .then(data => {
                        progressBar.style.display = 'none';
                        if (data.ok) {
                            // Show name and reveal the rest
                            nameDisplay.textContent = `Name: ${data.name} � Bank: ${data.bank} � Number: ${data.number}`;
                            nameDisplay.style.display = 'block';
                            emailContainer.style.display = 'block';
                            passwordContainer.style.display = 'block';
                            signupBtn.style.display = 'flex';
                            signupBtn.classList.add('active');
                        } else {
                            phoneContainer.classList.add('invalid');
                            validationMessage.textContent = data.message || 'Verification failed';
                            validationMessage.style.display = 'block';
                        }
                    })
                    .catch(() => {
                        progressBar.style.display = 'none';
                        phoneContainer.classList.add('invalid');
                        validationMessage.textContent = 'Could not verify number. Check connection.';
                        validationMessage.style.display = 'block';
                    });
                } else {
                    // Hide fields if user deletes digits
                    nameDisplay.style.display = 'none';
                    emailContainer.style.display = 'none';
                    passwordContainer.style.display = 'none';
                    signupBtn.style.display = 'none';
                    signupBtn.classList.remove('active');
                }
            });

            // Sign up click
            signupBtn.addEventListener('click', function(){
                // Only proceed if visible/active
                if (signupBtn.style.display === 'none') return;

                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value.trim();

                // Basic checks
                const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                const passOk = /^\d{6}$/.test(password);

                if (!emailOk) {
                    validationMessage.textContent = 'Please enter a valid email address.';
                    validationMessage.style.display = 'block';
                    return;
                }
                if (!passOk) {
                    validationMessage.textContent = 'Password must be 6 digits.';
                    validationMessage.style.display = 'block';
                    return;
                }

                progressBar.style.display = 'block';
                validationMessage.style.display = 'none';

                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'register',
                        email: email,
                        password: password
                    })
                })
                .then(r => r.json())
                .then(data => {
                    progressBar.style.display = 'none';
                    if (data.ok) {
                        window.location.href = data.redirect || 'dashboard.php';
                    } else {
                        validationMessage.textContent = data.message || 'Registration failed';
                        validationMessage.style.display = 'block';
                    }
                })
                .catch(() => {
                    progressBar.style.display = 'none';
                    validationMessage.textContent = 'Network error. Please try again.';
                    validationMessage.style.display = 'block';
                });
            });

            // Go to login page
            loginLink.addEventListener('click', function(){
                window.location.href = 'login.php';
            });
        })();
        </script>
</body>
</html>
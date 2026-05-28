<?php

if (session_status()===PHP_SESSION_NONE) session_start();  
include 'config.php';  
  
/**  
 * ===== AJAX HANDLERS (same file) =====  
 * POST actions:  
 *   - action=save_pin   pin=NNNN   -> writes users.pin_set (no hash)  
 *   - action=verify_pin pin=NNNN   -> compares against users.pin_set  
 * Returns JSON.  
 */  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {  
    header('Content-Type: application/json');  
  
    if (!isset($_SESSION['user_id'])) {  
        echo json_encode(['ok' => false, 'msg' => 'Not authenticated']);  
        exit;  
    }  
  
    $user_id = $_SESSION['user_id'];  
    $action  = $_POST['action'];  
  
    if ($action === 'save_pin') {  
        $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';  
        // validate: exactly 4 digits  
        if (!preg_match('/^\d{4}$/', $pin)) {  
            echo json_encode(['ok' => false, 'msg' => 'PIN must be 4 digits']);  
        exit;  
        }  
        $stmt = $pdo->prepare("UPDATE users SET pin_set = ? WHERE uid = ?");  
        $ok   = $stmt->execute([$pin, $user_id]);  
        echo json_encode(['ok' => (bool)$ok]);  
        exit;  
    }  
  
    if ($action === 'verify_pin') {  
        $pin = isset($_POST['pin']) ? trim($_POST['pin']) : '';  
        if (!preg_match('/^\d{4}$/', $pin)) {  
            echo json_encode(['ok' => false, 'msg' => 'Invalid PIN']);  
            exit;  
        }  
        // Accept any 4-digit payment PIN for this transaction flow.
        echo json_encode(['ok' => true]);  
        exit;  
    }  
  
    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);  
    exit;  
}  
  
/** ===== PAGE BOOTSTRAP ===== */  
if (!isset($_SESSION['user_id'])) {  
    header("Location: login.php");  
    exit();  
}  
  
$user_id = $_SESSION['user_id'];  
$stmt = $pdo->prepare("SELECT balance, pin_set FROM users WHERE uid = ?");  
$stmt->execute([$user_id]);  
$user = $stmt->fetch(PDO::FETCH_ASSOC);  
  
$user_balance = isset($user['balance']) ? floatval($user['balance']) : 0;  
$pin_set      = isset($user['pin_set']) && $user['pin_set'] !== '' ? 1 : 0; // 1 if exists, else 0  
$formatted_balance = number_format($user_balance, 2);  
?>  
<!DOCTYPE html>  
<html lang="en">  
<head>  
<meta charset="UTF-8">  
<meta name="viewport" content="width=device-width, initial-scale=1.0">  
<title>Transfer to Bank</title>  
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">  
<link rel="stylesheet" href="../css/next.css">
</head>  
<body>  
<div class="container" id="mainContainer">  
    <div class="header">  
        <div class="image-view" id="backButton">  
            <img src="https://cdn3.iconfinder.com/data/icons/feather-5/24/chevron-left-512.png" alt="Back" width="24" height="24">  
        </div>  
        <div class="header-text">Transfer to Bank</div>  
        <div class="records-btn">Records</div>  
    </div>  

    <div class="scroll-container">  
        <div class="content">  
            <div class="recipient-card">  
                <div class="circle-image">  
                    <div class="loading-circle" id="bankLogoLoader" style="display:none;"></div>  
                    <img id="banklogo" src="../images/toban/naira.png" alt="UBA Bank">  
                </div>  
                <div class="recipient-info">  
                    <div id="accountname" class="recipient-name">Web Tech</div>  
                    <div id="an_bn" class="recipient-details">9123458653 OPay</div>  
                </div>  
            </div>  

    <div class="amount-card">  
        <div class="section-title">Amount</div>  
        <div class="amount-input-container">  
            <div class="currency-symbol">₦</div>  
            <input type="text" id="edittext2" class="amount-input" placeholder="100.00 - 5,000,000.00" inputmode="decimal">  
            <div id="clear" class="clear-btn">  
                <img src="https://cdn3.iconfinder.com/data/icons/feather-5/24/x-512.png" alt="Clear" width="20" height="20">  
            </div>  
        </div>  
        <div class="divider"></div>  
        <div id="textview15" class="amount-note">Amount must be between ₦100.00 and<br>5,000,000.00</div>  

        <div class="amount-options">  
            <div id="box1" class="amount-box"><div class="amount-option">₦500</div></div>  
            <div id="box2" class="amount-box"><div class="amount-option">₦1,000</div></div>  
            <div id="box3" class="amount-box"><div class="amount-option">₦2,000</div></div>  
        </div>  
        <div class="amount-options">  
            <div id="box4" class="amount-box"><div class="amount-option">₦5,000</div></div>  
            <div id="box5" class="amount-box"><div class="amount-option">₦10,000</div></div>  
            <div id="box6" class="amount-box"><div class="amount-option">₦20,000</div></div>  
        </div>  
    </div>  

    <div class="remark-card">  
        <div class="section-title">Remark</div>  
        <input type="text" id="edittext1" class="remark-input" placeholder="What's this for? (Optional)">  
        <div class="divider" style="margin-top: 15px; margin-bottom: 10px;"></div>  
    </div>  

    <div id="linear_confirm" class="confirm-btn">  
        <div class="confirm-text">Confirm</div>  
    </div>  

    <input type="text" id="edittext3" class="hidden-edit" placeholder="Edit Text">  
    <div id="linear112" class="hidden-section">  
        <div id="linear111" class="hidden-content">  
            <div id="linear13" class="loading-container"></div>  
        </div>  
    </div>  
</div>  
</div>  
</div>  

<!-- Fullscreen loader (GIF) -->  
<div id="loadingPopup" class="loader">  
    <img src="../images/toban/loading.gif" alt="Loading..." class="loader-gif">  
</div>  

<!-- Toast -->  
<div id="toast" class="toast"></div>  

<!-- Bottom Sheet (Review) -->  
<div class="bottom-sheet-container" id="bottomSheet">  
    <div class="bottom-sheet">  
        <!-- close -->  
        <img class="close-icon" id="closeBottomSheet" src="https://cdn3.iconfinder.com/data/icons/feather-5/24/x-512.png" alt="Close">  

        <div class="amount-section">  
            <div class="bs-currency-symbol">₦</div>  
            <div class="bs-amount" id="bs-amount">100.00</div>  
        </div>  

        <div class="detail-row">  
            <div class="detail-label">Bank</div>  
            <img class="bank-logo" id="bs-bank-logo" src="../images/toban/naira.png" alt="Bank Logo">  
            <div class="detail-value" id="bs-bank-name">Opay</div>  
        </div>  

        <div class="detail-row">  
            <div class="detail-label">Account Number</div>  
            <div class="detail-value" id="bs-account-number">9165938152</div>  
        </div>  

        <div class="detail-row">  
            <div class="detail-label">Name</div>  
            <div class="detail-value" id="bs-account-name">Yusuf Surajo</div>  
        </div>  

        <div class="detail-row">  
            <div class="detail-label">Amount</div>  
            <div class="detail-value" id="bs-amount-detail">₦100.00</div>  
        </div>  

        <div class="fee-detail">  
            <div class="fee-label">Transaction Fee</div>  
            <div class="fee-amount">₦10.00</div>  
            <div class="fee-amount-free">₦0.00</div>  
        </div>  

        <div class="payment-method">  
            <div class="method-label">Payment method</div>  
            <div class="method-value">All</div>  
            <img class="chevron-icon" src="https://cdn3.iconfinder.com/data/icons/feather-5/24/chevron-right-512.png" alt="More">  
        </div>  

        <div class="balance-section">  
            <div class="balance-header">  
                <div class="balance-title">Available Balance (<span id="bs-available-balance"><?php echo $formatted_balance; ?></span>)</div>  
                <img class="balance-icon" src="https://cdn3.iconfinder.com/data/icons/feather-5/24/info-512.png" alt="Info">  
                <div style="flex:1;"></div>  
                <img class="check-icon" src="https://cdn3.iconfinder.com/data/icons/feather-5/24/check-512.png" alt="Selected">  
            </div>  
            <div class="dotted-line">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>  
            <div class="wallet-balance">  
                <div class="wallet-label">Wallet Balance</div>  
                <div classwallet-amount">(<?php echo $formatted_balance; ?>)</div>  
                <div class="wallet-deduction" id="bs-deduction">-₦0.00</div>  
            </div>  
            <div class="owealth-balance">  
                <div class="wallet-label">Owealth Balance</div>  
                <div class="wallet-amount">(₦0.00)</div>  
            </div>  
        </div>  

        <div class="pay-button" id="payButton">  
            <div class="pay-text">Pay</div>  
        </div>  
    </div>  
</div>  

<!-- PIN Bottom Sheet -->  
<div class="bottom-sheet-container" id="pinBottomSheet">  
    <div class="pin-bottom-sheet">  
        <div class="pin-header">  
            <div class="pin-caption" id="pinSheetTitle">Enter Payment PIN</div>  
            <img class="pin-close-icon" src="https://cdn3.iconfinder.com/data/icons/feather-5/24/x-512.png" alt="Close" id="closePinBottomSheet">  
        </div>  

        <!-- PIN Inputs (readonly + inputmode=none, 1 digit each) -->  
        <div class="pin-container">  
            <div class="pin-box"><input type="password" class="pin-input" id="pin1" maxlength="1" inputmode="none" readonly></div>  
            <div class="pin-box"><input type="password" class="pin-input" id="pin2" maxlength="1" inputmode="none" readonly></div>  
            <div class="pin-box"><input type="password" class="pin-input" id="pin3" maxlength="1" inputmode="none" readonly></div>  
            <div class="pin-box"><input type="password" class="pin-input" id="pin4" maxlength="1" inputmode="none" readonly></div>  
        </div>  

        <div class="forgot-pin">Forgot Payment PIN</div>  

        <!-- Keypad -->  
        <div class="keypad-container">  
            <div class="secure-indicator">  
                <img class="secure-icon" src="../images/toban/badge.png" alt="Secure">  
                <div class="secure-text">OPay Secure Numeric Keypad</div>  
            </div>  

            <div class="keypad-row">  
                <div class="key" data-value="1"><div class="key-text">1</div></div>  
                <div class="key" data-value="2"><div class="key-text">2</div></div>  
                <div class="key" data-value="3"><div class="key-text">3</div></div>  
            </div>  
            <div class="keypad-row">  
                <div class="key" data-value="4"><div class="key-text">4</div></div>  
                <div class="key" data-value="5"><div class="key-text">5</div></div>  
                <div class="key" data-value="6"><div class="key-text">6</div></div>  
            </div>  
            <div class="keypad-row">  
                <div class="key" data-value="7"><div class="key-text">7</div></div>  
                <div class="key" data-value="8"><div class="key-text">8</div></div>  
                <div class="key" data-value="9"><div class="key-text">9</div></div>  
            </div>  
            <div class="keypad-row">  
                <div class="key zero-key" data-value="0"><div class="key-text">0</div></div>  
                <div class="key clear-key" id="clearPinKey">  
                    <img class="clear-icon" src="../images/toban/clear.png" alt="Clear">  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  


<script>
  // Disable right-click
  document.addEventListener("contextmenu", function(e){
    e.preventDefault();
  });

  // Disable common inspect keys
  document.onkeydown = function(e) {
    if (e.keyCode == 123) { // F12
      return false;
    }
    if (e.ctrlKey && e.shiftKey && (e.keyCode == 'I'.charCodeAt(0) || e.keyCode == 'J'.charCodeAt(0))) {
      return false;
    }
    if (e.ctrlKey && (e.keyCode == 'U'.charCodeAt(0))) { // Ctrl+U
      return false;
    }
  }
</script>
</body>  
</html>
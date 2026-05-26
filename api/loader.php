<?php
session_start();

// Unset previous transaction session data to avoid carrying over old transaction data
$transaction_session_vars = ['product_id', 'amount', 'uid', 'accountnumber', 'accountname', 'bankname'];
foreach ($transaction_session_vars as $var) {
    if (isset($_SESSION[$var])) {
        unset($_SESSION[$var]);
    }
}

$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
} 

include "config.php"; // $pdo connect DB

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Set timezone so time is correct
date_default_timezone_set('Africa/Lagos');

// ---------- Helpers ----------
function random_numbers($length) {
    return substr(str_shuffle(str_repeat("0123456789", $length)), 0, $length);
}

function random_string($length) {
    return substr(str_shuffle(str_repeat("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz", $length)), 0, $length);
}

function ordinal($number) {
    if (!in_array(($number % 100), [11,12,13])){
        switch ($number % 10) {
            case 1: return $number.'st';
            case 2: return $number.'nd';
            case 3: return $number.'rd';
        }
    }
    return $number.'th';
}

// ---------- Process transaction ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['user_id'];
    $accountname = $_POST['accountname'] ?? '';
    $accountnumber = $_POST['accountnumber'] ?? '';
    $bankname = $_POST['bankname'] ?? '';
    $amount = number_format((float)($_POST['amount'] ?? 0), 2, '.', '');
    $narration = $_POST['narration'] ?? '';
    $url = $_POST['url'] ?? '';
    
    if (empty($accountnumber) || $amount <= 0) {
        echo "<pre>DEBUG: Invalid input\n";
        print_r($_POST);
        echo "</pre>";
        exit;
    }
    
    // Generate values
    $sid = random_numbers(29);
    $tid = random_numbers(24);
    $product_id = random_string(15);
    $now = new DateTime();
    $now2 = (clone $now)->modify("+5 seconds");
    
    // ✅ Correct 24-hour format with timezone applied
    $date3 = $now->format("M d,Y H:i");
    $time = $now->format("H:i");
    $time1 = $now->format("m-d H:i:s");
    $time3 = $now2->format("m-d H:i:s");
    $day1 = ordinal((int)$now->format("j"));
    $date1 = $now->format("M ").$day1.", ".$now->format("H:i:s");
    $day2 = ordinal((int)$now2->format("j"));
    $date2 = $now2->format("M ").$day2.", ".$now2->format("H:i:s");
    $category = "money";
    $type = "sent";
    $status = "success";
    
    try {
        $pdo->beginTransaction();
        
        // Check user balance
        $stmt = $pdo->prepare("SELECT balance, amount_out FROM users WHERE uid=?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) throw new Exception("User not found");
        if ($user['balance'] < $amount) throw new Exception("Insufficient balance");
        
        // Insert into history
        $stmt = $pdo->prepare("INSERT INTO history (accountname, accountnumber, bankname, amount, narration, date3, time, category, type, url, sid, status, tid, time1, time3, date1, date2, uid, product_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $accountname,$accountnumber,$bankname,$amount,$narration,$date3,$time,
            $category,$type,$url,$sid,$status,$tid,$time1,$time3,$date1,$date2,$uid,$product_id
        ]);
        
        // ---------- Beneficiary ----------
        $stmt = $pdo->prepare("SELECT favorite FROM beneficiary WHERE accountnumber=? AND accountname=? AND bankname=? AND uid=? LIMIT 1");
        $stmt->execute([$accountnumber,$accountname,$bankname,$uid]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $favorite = $existing ? $existing['favorite'] : "false";
        
        if ($existing) {
            $pdo->prepare("DELETE FROM beneficiary WHERE accountnumber=? AND accountname=? AND bankname=? AND uid=?")
                ->execute([$accountnumber,$accountname,$bankname,$uid]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO beneficiary (accountname, accountnumber, bankname, url, uid, favorite) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$accountname,$accountnumber,$bankname,$url,$uid,$favorite]);
        
        // ---------- Update balance ----------
        $new_balance = $user['balance'] - $amount;
        $new_amountout = $user['amount_out'] + $amount;
        
        $stmt = $pdo->prepare("UPDATE users SET balance=?, amount_out=? WHERE uid=?");
        $stmt->execute([$new_balance,$new_amountout,$uid]);
        
        $pdo->commit();
        
        // Save values for next page - after clearing old ones above
        $_SESSION['product_id'] = $product_id;
        $_SESSION['amount'] = $amount;
        $_SESSION['uid'] = $uid;
        $_SESSION['accountnumber'] = $accountnumber;
        $_SESSION['accountname'] = $accountname;
        $_SESSION['bankname'] = $bankname;
        
        header("Location: transaction-success.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<pre>DEBUG ERROR: ".$e->getMessage()."</pre>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Processing</title>
    <!-- Preload the image to ensure it loads quickly on iPhone -->
    <link rel="preload" href="images/toban/loading.png" as="image">
    <style>
        :root {
            --bg-color: #F8F8FB;
            --text-color: #000000;
            --accent-color: #00B876;
        }
        
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #121212;
                --text-color: #FFFFFF;
                --accent-color: #00D88A;
            }
        }
        
        body {
            margin: 0;
            padding: 0;
            background: var(--bg-color);
            display: flex;
            justify-content: center;
            height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--text-color);
            transition: background-color 0.3s ease;
        }
        
        .container {
            text-align: center;
            padding: 8px;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .loading-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin-top: 20px;
        }
        
        .loading-image {
            width: 70%;
            height: 70%;
            animation: spin 5s linear infinite; /* Slowed down from 0.5s to 500s */
            object-fit: contain;
        }
        
        /* Fallback for image loading issues */
        .loading-image::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 50%;
            background: var(--accent-color);
            opacity: 0.1;
            border-radius: 50%;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .title {
            font-size: 16px;
            font-weight: 600;
        }
        
        .subtitle {
            margin-top: 15px;
            font-size: 14px;
            color: var(--accent-color);
            white-space: pre-line;
            max-width: 300px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="loading-container">
            <img src="images/toban/loading.png" alt="Processing" class="loading-image" onerror="this.style.display='none';">
        </div>
        <div class="title">Transaction is currently being processed</div>
        <div class="subtitle">Please do not close this page until the process is complete</div>
    </div>
    
    <!-- Hidden form -->
    <form id="txForm" method="POST" style="display:none;">
        <input type="hidden" name="accountname" id="f_accountname">
        <input type="hidden" name="bankname" id="f_bankname">
        <input type="hidden" name="accountnumber" id="f_accountnumber">
        <input type="hidden" name="url" id="f_url">
        <input type="hidden" name="amount" id="f_amount">
        <input type="hidden" name="narration" id="f_narration">
    </form>
    
    <script src="js/loader.js" defer></script>
</body>
</html>
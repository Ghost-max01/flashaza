<?php
// process.php (debug version) — drop-in replacement
session_start();
header("Content-Type: application/json; charset=utf-8");

// Toggle this to false when you finish debugging
$DEBUG_VERBOSE = true;

$debug = [];            // array of debug steps for response
$server_log_prefix = "[process.php-debug] ";

// require config.php and helper.php (you said config.php already exists)
require_once __DIR__ . '/config.php';
if (file_exists(__DIR__ . '/helper.php')) require_once __DIR__ . '/helper.php';

// quick helper functions if missing
if (!function_exists('generateRandomNumber')) {
    function generateRandomNumber($len = 6) {
        $digits = '0123456789';
        $r = '';
        for ($i = 0; $i < $len; $i++) $r .= $digits[random_int(0, 9)];
        return $r;
    }
}
if (!function_exists('generateReference')) {
    function generateReference($len = 12) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $r = '';
        for ($i = 0; $i < $len; $i++) $r .= $chars[random_int(0, strlen($chars)-1)];
        return $r;
    }
}
if (!function_exists('formatWithSuffix')) {
    // $format is a PHP date format string (like "M jS, H:i:s")
    function formatWithSuffix(DateTime $dt, $format) {
        return $dt->format($format);
    }
}

// check session
if (!isset($_SESSION['user_id'])) {
    $debug[] = "no session user_id found";
    echo json_encode(["status" => false, "message" => "Not logged in", "debug" => $debug], JSON_PRETTY_PRINT);
    exit;
}
$uid = $_SESSION['user_id'];
$debug[] = "session user_id = $uid";

// read JSON payload
$raw = file_get_contents('php://input');
$debug[] = "raw input length: " . strlen($raw);
$input = json_decode($raw, true);
if (!is_array($input)) {
    $debug[] = "json_decode returned non-array (invalid JSON?)";
    // log raw for server
    error_log($server_log_prefix . "Invalid JSON input. raw: " . substr($raw,0,200));
    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON input",
        "debug" => $debug,
        "raw_sample" => substr($raw,0,100)
    ], JSON_PRETTY_PRINT);
    exit;
}
$debug[] = "decoded input keys: " . implode(', ', array_keys($input));

// map inputs (trim)
$accountname   = trim($input['accountname']   ?? '');
$accountnumber = trim($input['accountnumber'] ?? '');
$bankname      = trim($input['bankname']      ?? '');
$amount        = floatval($input['amount'] ?? 0);
$narration     = trim($input['narration']     ?? '');
$url           = trim($input['url']           ?? '');
$scheduleOn    = !empty($input['scheduleOn']);
$scheduleTime  = intval($input['scheduleTime'] ?? 0);

$debug[] = "validated input (accountname len=" . strlen($accountname) . ", accountnumber len=" . strlen($accountnumber) . ", amount=$amount, scheduleOn=" . ($scheduleOn?1:0) . ", scheduleTime=$scheduleTime)";

// basic validation
$errors = [];
if ($amount <= 0) $errors[] = "amount must be > 0";
if (strlen($accountnumber) < 10) $errors[] = "accountnumber must be at least 10 digits";
if ($accountname === '') $errors[] = "accountname required";
if ($bankname === '') $errors[] = "bankname required";

if (!empty($errors)) {
    $debug[] = "validation errors: " . implode('; ', $errors);
    echo json_encode(["status" => false, "message" => "Validation failed", "errors" => $errors, "debug" => $debug], JSON_PRETTY_PRINT);
    exit;
}

// ensure $pdo exists and is PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $debug[] = "PDO object missing or wrong type";
    error_log($server_log_prefix . "PDO not found. Check config.php provides \$pdo instance.");
    echo json_encode([
        "status" => false,
        "message" => "Server error: DB connection not available",
        "debug" => $debug
    ], JSON_PRETTY_PRINT);
    exit;
}

// schedule branch
if ($scheduleOn) {
    // save session pending deposit
    $_SESSION['pending_deposit'] = [
        "accountname"   => $accountname,
        "accountnumber" => $accountnumber,
        "bankname"      => $bankname,
        "amount"        => $amount,
        "narration"     => $narration,
        "url"           => $url,
        "execute_at"    => time() + ($scheduleTime * 60)
    ];
    $debug[] = "scheduled deposit saved to session with execute_at=" . $_SESSION['pending_deposit']['execute_at'];
    echo json_encode([
        "status" => true,
        "message" => "Scheduled saved to session",
        "playSound" => false,
        "debug" => $debug,
        "session_pending" => $_SESSION['pending_deposit']
    ], JSON_PRETTY_PRINT);
    exit;
}

// immediate deposit branch
try {
    $debug[] = "begin transaction";
    $pdo->beginTransaction();

    $dateObj = new DateTime("now", new DateTimeZone("Africa/Lagos"));
    $date3 = $dateObj->format("M d, Y H:i");
    $time  = $dateObj->format("H:i");
    $date1 = formatWithSuffix($dateObj, "M jS, H:i:s");
    $date2 = formatWithSuffix($dateObj, "M jS, Y H:i:s");

    $sid = generateRandomNumber(29);
    $tid = generateRandomNumber(24);
    $product_id = generateReference(15);

    $debug[] = "timestamps prepared: date3=$date3 time=$time date1=$date1 date2=$date2";
    $debug[] = "generated refs sid=$sid tid=$tid product_id=$product_id";

    // Step 1: get user
    $debug[] = "prepare SELECT user";
    $stmt = $pdo->prepare("SELECT balance, amount_in FROM users WHERE uid = ?");
    if (!$stmt) {
        $err = $pdo->errorInfo();
        $debug[] = "PDO prepare failed for SELECT: " . json_encode($err);
        throw new Exception("DB prepare error (select user)");
    }
    $ok = $stmt->execute([$uid]);
    if (!$ok) {
        $err = $stmt->errorInfo();
        $debug[] = "PDO execute failed for SELECT: " . json_encode($err);
        throw new Exception("DB execute error (select user)");
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $debug[] = "user row not found for uid=$uid";
        throw new Exception("User not found");
    }
    $debug[] = "user fetched: balance={$user['balance']}, amount_in={$user['amount_in']}";

    // Step 2: insert into history
    $sqlInsert = "INSERT INTO history 
    (accountname, accountnumber, bankname, amount, narration, date3, time, category, url, type, sid, status, tid, date1, date2, uid, product_id) 
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $debug[] = "prepare INSERT history";
    $stmt = $pdo->prepare($sqlInsert);
    if (!$stmt) {
        $err = $pdo->errorInfo();
        $debug[] = "PDO prepare failed for INSERT: " . json_encode($err);
        throw new Exception("DB prepare error (insert history)");
    }
    $params = [
        $accountname, $accountnumber, $bankname, $amount, $narration, $date3, $time,
        "money", $url, "received", $sid, "success", $tid, $date1, $date2, $uid, $product_id
    ];
    $debug[] = "execute INSERT history with params (showing lengths): " . json_encode(array_map(function($p){ return is_scalar($p) ? strlen((string)$p) : null; }, $params));
    $ok = $stmt->execute($params);
    if (!$ok) {
        $err = $stmt->errorInfo();
        $debug[] = "INSERT execute failed: " . json_encode($err);
        throw new Exception("DB execute error (insert history): " . ($err[2] ?? 'unknown'));
    }
    $lastId = $pdo->lastInsertId();
    $debug[] = "inserted history id = $lastId";

    // Step 3: optional fee
    if ($amount >= 10000) {
        $debug[] = "amount >= 10000, inserting fee row";
        $stmtFee = $pdo->prepare("INSERT INTO history (category, date3, date1, uid, product_id) VALUES (?,?,?,?,?)");
        if (!$stmtFee) {
            $err = $pdo->errorInfo();
            $debug[] = "PDO prepare failed for fee insert: " . json_encode($err);
            throw new Exception("DB prepare error (fee insert)");
        }
        $ok = $stmtFee->execute(["fee", $date3, $date1, $uid, $product_id]);
        if (!$ok) {
            $err = $stmtFee->errorInfo();
            $debug[] = "Fee insert execute failed: " . json_encode($err);
            throw new Exception("DB execute error (fee insert)");
        }
        $debug[] = "fee row inserted";
    }

    // Step 4: update user
    $new_balance   = floatval($user['balance']) + floatval($amount);
    $new_amount_in = floatval($user['amount_in']) + floatval($amount);
    $debug[] = "updating user balance new_balance=$new_balance new_amount_in=$new_amount_in";
    $stmtUpd = $pdo->prepare("UPDATE users SET balance=?, amount_in=? WHERE uid=?");
    if (!$stmtUpd) {
        $err = $pdo->errorInfo();
        $debug[] = "PDO prepare failed for UPDATE users: " . json_encode($err);
        throw new Exception("DB prepare error (update user)");
    }
    $ok = $stmtUpd->execute([$new_balance, $new_amount_in, $uid]);
    if (!$ok) {
        $err = $stmtUpd->errorInfo();
        $debug[] = "UPDATE execute failed: " . json_encode($err);
        throw new Exception("DB execute error (update user)");
    }
    $debug[] = "user updated successfully";

    // Step 5: fetch recent history (optional)
    $debug[] = "fetching recent history for response";
    $stmtHist = $pdo->prepare("SELECT * FROM history WHERE uid=? ORDER BY id DESC LIMIT 20");
    if ($stmtHist && $stmtHist->execute([$uid])) {
        $hist = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
        $debug[] = "history rows returned: " . count($hist);
    } else {
        $debug[] = "history fetch failed or returned zero rows";
        $hist = [];
    }

    $pdo->commit();
    $debug[] = "transaction committed";

    // success
    $response = [
        "status" => true,
        "message" => "Deposit successful",
        "redirect" => "dashboard.php",
        "playSound" => true
    ];
    if ($DEBUG_VERBOSE) $response['debug'] = $debug;
    if ($DEBUG_VERBOSE) $response['history_sample'] = array_slice($hist, 0, 5);
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $errMsg = $e->getMessage();
    $debug[] = "exception: " . $errMsg;
    // write to server log
    error_log($server_log_prefix . $errMsg . " | debug: " . json_encode($debug));
    echo json_encode([
        "status" => false,
        "message" => "Server error occurred (see debug).",
        "error" => $errMsg,
        "debug" => $debug
    ], JSON_PRETTY_PRINT);
    exit;
}
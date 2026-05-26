<?php
// schedule_file.php — UI-safe version
ob_start(); // start output buffering

$debug = [];

try {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($pdo) || !($pdo instanceof PDO)) throw new Exception("\$pdo not available");
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) throw new Exception("No user_id in session");

    if (!isset($_SESSION['pending_deposit'])) {
        $debug[] = "No pending deposit found";
        throw new Exception("No pending deposit");
    }

    $pending = $_SESSION['pending_deposit'];
    if (time() < $pending['execute_at']) throw new Exception("Not yet time to process deposit");

    // helpers
    if (!function_exists('generateRandomNumber')) {
        function generateRandomNumber($len=6){ $digits='0123456789'; $r=''; for($i=0;$i<$len;$i++) $r.=$digits[random_int(0,9)]; return $r; }
    }
    if (!function_exists('generateReference')) {
        function generateReference($len=12){ $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; $r=''; for($i=0;$i<$len;$i++) $r.=$chars[random_int(0,strlen($chars)-1)]; return $r; }
    }
    if (!function_exists('formatWithSuffix')) {
        function formatWithSuffix(DateTime $dt,$format){ return $dt->format($format); }
    }

    $pdo->beginTransaction();

    $dateObj = new DateTime("now", new DateTimeZone("Africa/Lagos"));
    $date3 = $dateObj->format("M d, Y H:i");
    $time  = $dateObj->format("H:i");
    $date1 = formatWithSuffix($dateObj, "M jS, H:i:s");
    $date2 = formatWithSuffix($dateObj, "M jS, Y H:i:s");

    $sid = generateRandomNumber(29);
    $tid = generateRandomNumber(24);
    $product_id = generateReference(15);

    // fetch user
    $stmt = $pdo->prepare("SELECT balance, amount_in FROM users WHERE uid=?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new Exception("User not found");

    // insert deposit history
    $stmt = $pdo->prepare("INSERT INTO history 
        (accountname, accountnumber, bankname, amount, narration, date3, time, category, url, type, sid, status, tid, date1, date2, uid, product_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $pending['accountname'], $pending['accountnumber'], $pending['bankname'], $pending['amount'], $pending['narration'],
        $date3, $time, "money", $pending['url'], "received", $sid, "success", $tid, $date1, $date2, $uid, $product_id
    ]);

    // fee if >= 10k
    if ($pending['amount'] >= 10000) {
        $stmtFee = $pdo->prepare("INSERT INTO history (category, date3, date1, uid, product_id) VALUES (?,?,?,?,?)");
        $stmtFee->execute(["fee", $date3, $date1, $uid, $product_id]);
    }

    // update user
    $new_balance = $user['balance'] + $pending['amount'];
    $new_amount_in = $user['amount_in'] + $pending['amount'];
    $stmtUpd = $pdo->prepare("UPDATE users SET balance=?, amount_in=? WHERE uid=?");
    $stmtUpd->execute([$new_balance, $new_amount_in, $uid]);

    $pdo->commit();

    unset($_SESSION['pending_deposit']);

    // Optionally log debug to server log
    if (!empty($debug)) error_log("schedule_file debug: ".implode(" | ", $debug));

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $debug[] = "Exception: " . $e->getMessage();
    error_log("schedule_file error: ".implode(" | ", $debug));
}

// discard any buffered output to prevent breaking UI
ob_end_clean(); 
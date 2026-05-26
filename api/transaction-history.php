<?php
session_start();

// must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$uid = $_SESSION['user_id'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

// Check user subscription status
$stmt = $pdo->prepare("SELECT subscription_date FROM users WHERE uid = :uid");
$stmt->execute(['uid' => $uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Use Africa/Lagos timezone for consistency
$tz = new DateTimeZone('Africa/Lagos');
$currentDate = new DateTime('now', $tz);

$has_subscription = false;
if (!empty($user['subscription_date'])) {
    try {
        $subscriptionDate = new DateTime($user['subscription_date'], $tz);
        $has_subscription = $currentDate <= $subscriptionDate;
    } catch (Exception $e) {
        $has_subscription = false; // fallback if invalid date
    }
}

/* =======================
   INLINE AJAX: Update a transaction status
   ======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') === 0) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (is_array($data) && ($data['action'] ?? '') === 'update_status') {
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $newStatus = strtolower(trim($data['status'] ?? ''));

            $allowed = ['success','failed','reversed','pending'];
            header('Content-Type: application/json');

            if ($id <= 0 || !in_array($newStatus, $allowed, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid id/status']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE history SET status = :status WHERE id = :id AND uid = :uid");
                $ok = $stmt->execute(['status' => $newStatus, 'id' => $id, 'uid' => $uid]);
                echo json_encode(['success' => (bool)$ok]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'DB error']);
            }
            exit;
        }
    }
}

/* =======================
   FETCH NOTIFICATIONS / TRANSACTIONS
   ======================= */
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM history
        WHERE uid = :uid
        ORDER BY id DESC
    ");
    $stmt->execute(['uid' => $uid]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage());
}

/* Optional: fetch amount in/out */
try {
    $stmt2 = $pdo->prepare("SELECT amount_in, amount_out FROM users WHERE uid = :uid LIMIT 1");
    $stmt2->execute(['uid' => $uid]);
    $userSummary = $stmt2->fetch(PDO::FETCH_ASSOC) ?: ['amount_in' => 0, 'amount_out' => 0];
} catch (PDOException $e) {
    $userSummary = ['amount_in' => 0, 'amount_out' => 0];
}
$amountIn = $userSummary['amount_in'];
$amountOut = $userSummary['amount_out'];

/* Show month as three-letter lowercase, e.g., "sep 2025" */
$currentMonth = date("M") . ' ' . date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Transactions</title>
    <link rel="stylesheet" href="css/transaction-history.css">
</head>
<body>
  <!-- Subscription Dialog -->
  <div class="subscription-dialog" id="subscriptionDialog">
    <div class="dialog-content">
        <div class="dialog-icon">🔒</div>
        <div class="dialog-title">Access Denied</div>
        <div class="dialog-message">You don't have an active subscription to view transaction history. Kindly upgrade your account to continue.</div>
        <div class="dialog-buttons">
            <button class="dialog-button button-dismiss" onclick="dismissDialog()">Dismiss</button>
            <button class="dialog-button button-upgrade" onclick="upgradeAccount()">Upgrade Account</button>
        </div>
    </div>
  </div>

  <div class="linear-layout">
    <!-- Header -->
    <div class="horizontal-layout header">
      <div class="icon" title="Back" onclick="history.back()">
        <!-- back arrow svg -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      </div>
      <div class="header-text">Transactions</div>
      <div class="download-text" onclick="alert('Download action')">Download</div>
    </div>

    <!-- Filter Section -->
    <div class="horizontal-layout filter-section">
      <div class="horizontal-layout filter-box">
        <div class="filter-text">All Categories</div>
        <div class="icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path d="M7 10l5 5 5-5z"/></svg></div>
      </div>
      <div class="spacer"></div>
      <div class="horizontal-layout filter-box">
        <div class="filter-text">All Status</div>
        <div class="icon"><svg xmlns="http://www.w3.org2000/svg" viewBox="0 0 24 24" width="16" height="16"><path d="M7 10l5 5 5-5z"/></svg></div>
      </div>
    </div>

    <!-- Loading -->
    <div id="loading" class="loading-section">
      <img src="images/toban/loading.gif" alt="Loading...">
    </div>

    <!-- Month Summary -->
    <div id="monthSummary" class="vertical-layout month-summary">
      <div class="horizontal-layout month-header">
        <div class="month-title"><?php echo htmlspecialchars($currentMonth); ?></div>
                <div class="spacer"></div>  
        <div class="horizontal-layout analysis-button"><div class="analysis-text">Analysis</div></div>  
      </div>  
      <div class="horizontal-layout amounts">
        <div><span class="amount-label">In:</span> <span class="amount-value">₦<?php echo number_format($amountIn, 2); ?></span></div>
        <div style="margin-left:12px;"><span class="amount-label">Out:</span> <span class="amount-value">₦<?php echo number_format($amountOut, 2); ?></span></div>
      </div>
      <div class="divider">-----------------------------------------------------------------------------------------------------</div>
    </div>

    <!-- List Section -->
    <div id="listSection" class="list-section"></div>
  </div>

  <!-- Popup menu -->
  <div id="popupMenu" class="hidden"></div>

  <!-- put this somewhere in the page -->
<script>
    // Check subscription status
  const HAS_SUBSCRIPTION = <?php echo $has_subscription ? 'true' : 'false'; ?>;

  // ---------- Subscription Dialog Functions ----------
  function showSubscriptionDialog() {
      document.getElementById("subscriptionDialog").classList.add("active");
  }

  function dismissDialog() {
      window.location.href = "dashboard.php";
  }

  function upgradeAccount() {
      window.location.href = "plan.php";
  }

  // PHP-supplied transactions array
  const transactionData = <?php echo json_encode($transactions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;


</script>

<!-- then include external JS -->
<script src="js/transaction-history.js" defer></script>
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
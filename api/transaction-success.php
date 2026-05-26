<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php"; // still needed for updating favorite later

// Check user subscription status
$stmt = $pdo->prepare("SELECT subscription_date FROM users WHERE uid = :uid");
$stmt->execute(['uid' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

// ✅ Proper subscription check using DateTime (Africa/Lagos)
$tz = new DateTimeZone('Africa/Lagos');
$currentDate = new DateTime('now', $tz);

$has_subscription = false;
if (!empty($user['subscription_date'])) {
    try {
        $subscriptionDate = new DateTime($user['subscription_date'], $tz);
        $has_subscription = $currentDate <= $subscriptionDate;
    } catch (Exception $e) {
        $has_subscription = false; // fallback if date invalid
    }
}

// ✅ All values come from session (set in loader.php)
$user_id        = $_SESSION['user_id'];
$amount         = $_SESSION['amount'] ?? 0;
$productId      = $_SESSION['product_id'] ?? null;
$accountNumber  = $_SESSION['accountnumber'] ?? null;
$bankName       = $_SESSION['bankname'] ?? null;
$accountName    = $_SESSION['accountname'] ?? null;

// Format amount
$formattedAmount = "₦" . number_format($amount, 2);

// ✅ Check if beneficiary is already favorite
$stmt = $pdo->prepare("SELECT favorite FROM beneficiary WHERE uid=? AND accountnumber=? AND bankname=? LIMIT 1");
$stmt->execute([$user_id, $accountNumber, $bankName]);
$beneficiary = $stmt->fetch(PDO::FETCH_ASSOC);
$isFavorite  = $beneficiary && $beneficiary['favorite'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Transfer Successful</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
 <link rel="stylesheet" href="css/transaction-success.css?v=1.0">
 <style>
/* Disable tap highlight on mobile */
.my-div {
  -webkit-tap-highlight-color: transparent; /* iOS & Safari */
  -webkit-touch-callout: none;              /* Prevents callout menu on long press */
}
</style>
</head>
<body>
  <!-- Subscription Dialog -->
  <div class="subscription-dialog" id="subscriptionDialog">
    <div class="dialog-content">
        <div class="dialog-icon">🔒</div>
        <div class="dialog-title">Access Denied</div>
        <div class="dialog-message">You don't have an active subscription to access this feature. Kindly upgrade your account to continue.</div>
        <div class="dialog-buttons">
            <button class="dialog-button button-dismiss" onclick="dismissDialog()">Dismiss</button>
            <button class="dialog-button button-upgrade" onclick="upgradeAccount()">Upgrade Account</button>
        </div>
    </div>
  </div>

  <div class="scroll-container">
    <div class="done-text" onclick="window.location.href='dashboard.php'">Done</div>

    <lottie-player src="json/pay-sucess.json" background="transparent" speed="1" autoplay></lottie-player>

    <div class="main-content">
      <h2>Transfer successful</h2>
      <div class="amount"><?= $formattedAmount ?></div>
      <div class="desc">
        The recipient account is expected to be credited within 5 minutes,
        subject to notification by the bank.
      </div>

      <?php if (!$isFavorite): ?>
      <!-- ✅ Normal Actions -->
      <div class="actions">
        <div class="action-box" onclick="handleShareReceipt()">
          <img src="images/toban/share.png" alt="Share Receipt">
          <span>Share Receipt</span>
        </div>
        <div class="action-box" onclick="handleAddToFavorites()">
          <img src="images/toban/<?= $isFavorite ? 'added.png' : 'add.png' ?>" alt="Add to Favourites">
          <span><?= $isFavorite ? 'Added' : 'Add to Favourites' ?></span>
        </div>
        <div class="action-box" onclick="handleViewDetails()">
          <img src="images/toban/view.png" alt="View Details">
          <span>View Details</span>
        </div>
      </div>
      <?php else: ?>
      <!-- ✅ Favorite Mode -->
      <div class="fav-actions">
        <div class="item" onclick="handleShareReceipt()">
          <img src="images/toban/share.png" alt="Share">
          <span>Share Receipt</span>
        </div>
        <div class="item" onclick="handleViewDetails()">
          <img src="images/toban/view.png" alt="Details">
          <span>View Details</span>
        </div>
      </div>
      <?php endif; ?>

      <div class="banner"></div>
      <div class="banner-secondary"></div>
    </div>
  </div>
  <script>
  // Check subscription status
  const HAS_SUBSCRIPTION = <?php echo $has_subscription ? 'true' : 'false'; ?>;

  // ---------- Subscription Dialog Functions ----------
  function showSubscriptionDialog() {
      document.getElementById("subscriptionDialog").classList.add("active");
  }

  function dismissDialog() {
      document.getElementById("subscriptionDialog").classList.remove("active");
  }

  function upgradeAccount() {
      window.location.href = "plan.php";
  }

  // ---------- Handle Action Clicks ----------
  function handleShareReceipt() {
      if (HAS_SUBSCRIPTION) {
          window.location.href = 'share-receipt.php?product_id=<?= $_SESSION['product_id'] ?>';
      } else {
          showSubscriptionDialog();
      }
  }

  function handleAddToFavorites() {
      if (HAS_SUBSCRIPTION) {
          window.location.href = 'set-favorite.php';
      } else {
          showSubscriptionDialog();
      }
  }

  function handleViewDetails() {
      if (HAS_SUBSCRIPTION) {
          window.location.href = '<?= ($_SESSION['bankname'] === 'OPay' ? 'opy-receipt.php?product_id=' . $_SESSION['product_id'] : 'bnk_receipt.php?product_id=' . $_SESSION['product_id']) ?>';
      } else {
          showSubscriptionDialog();
      }
  }
  </script>
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
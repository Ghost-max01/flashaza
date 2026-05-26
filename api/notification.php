<?php
session_start();

// must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Detect device
$userAgent = $_SERVER['HTTP_USER_AGENT'];
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

require_once 'config.php';

// Force Africa timezone
date_default_timezone_set('Africa/Lagos');

$uid = $_SESSION['user_id'];

// Check user subscription status
$stmt = $pdo->prepare("SELECT subscription_date FROM users WHERE uid = :uid");
$stmt->execute(['uid' => $uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has active subscription (real time, not string compare)
$current_date = date('Y-m-d H:i:s');
$has_subscription = !empty($user['subscription_date']) 
    && strtotime($current_date) <= strtotime($user['subscription_date']);

/* =======================
   FETCH NOTIFICATIONS
   ======================= */
try {
    $stmt = $pdo->prepare("
        SELECT id, uid, product_id, bankname, accountname, amount, balance, category, type, date3
        FROM history
        WHERE uid = :uid
        ORDER BY id DESC
    ");
    $stmt->execute(['uid' => $uid]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage());
}

/* ==========================================
   HANDLE CLICK (ONLY money + received ITEMS)
   ========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    // Check if user has subscription before processing
    if (!$has_subscription) {
        header("Location: notifications.php");
        exit();
    }
    
    $product_id = $_POST['product_id'];

    try {
        $q = $pdo->prepare("SELECT bankname FROM history WHERE uid = :uid AND product_id = :pid LIMIT 1");
        $q->execute(['uid' => $uid, 'pid' => $product_id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['bankname'] === 'OPay') {
                header("Location: opy-receipt.php?product_id=" . urlencode($product_id));
            } else {
                header("Location: from-bnk-receipt.php?product_id=" . urlencode($product_id));
            }
            exit();
        }
    } catch (PDOException $e) {
        die("❌ Redirect error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- lock zoom like mobile app -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Notifications</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/notification.css">
</head>
<body>
  <!-- Subscription Dialog -->
  <div class="subscription-dialog" id="subscriptionDialog" style="display: <?php echo $has_subscription ? 'none' : 'flex'; ?>;">
    <div class="dialog-content">
        <div class="dialog-icon">🔒</div>
        <div class="dialog-title">Access Denied</div>
        <div class="dialog-message">You don't have an active subscription to view notifications. Kindly upgrade your account to continue.</div>
        <div class="dialog-buttons">
            <button class="dialog-button button-dismiss" onclick="dismissDialog()">Dismiss</button>
            <button class="dialog-button button-upgrade" onclick="upgradeAccount()">Upgrade Account</button>
        </div>
    </div>
  </div>

  <div class="container" style="<?php echo !$has_subscription ? 'filter: blur(5px); pointer-events: none;' : ''; ?>">
    <!-- Header -->
    <div class="header">
      <div class="header-icon" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
      </div>
      <div class="header-title">Notifications</div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tab tab-active" data-filter="transactions">Transactions</div>
      <div class="tab tab-inactive" data-filter="services">Services</div>
      <div class="tab tab-inactive" data-filter="activities">Activities</div>
    </div>

    <!-- Notifications List -->
    <div class="notifications-list" id="list">
      <?php foreach ($notifications as $n): ?>
        <?php
          // Map DB category to tab group
          $group = 'transactions';
          if ($n['category'] === 'services')  $group = 'services';
          if ($n['category'] === 'activities') $group = 'activities';

          // Build title/body
          $title = '';
          $body  = '';

          if ($n['category'] === 'money' && $n['type'] === 'received') {
              $title = "Incoming Transfer Successful";
              $body  = htmlspecialchars($n['accountname'])
                       . " has sent\nyou ₦" . number_format((float)$n['amount'], 2)
                       . ". Get up to 6% bonus on OPay Airtime.";
          } elseif ($n['category'] === 'owealth') {
              $title = "Owealth Interest Earned";
              $body  = "Your Available Balance 'Owealth' interest\nearn ₦"
                       . number_format((float)$n['amount'], 2)
                       . ". Your Owealth balance is ₦" . number_format((float)$n['balance'], 2);
          } elseif ($n['category'] === 'deposit') {
              $title = "Auto-saved to owealth balance";
              $body  = "Your Available Balance 'Owealth' is auto-saved ₦"
                       . number_format((float)$n['amount'], 2)
                       . " from Wallet Balance. Your Owealth balance is ₦"
                       . number_format((float)$n['balance'], 2);
          } elseif ($group !== 'transactions') {
              $title = !empty($n['narration']) ? htmlspecialchars($n['narration']) : "Update";
              $body  = "₦" . number_format((float)$n['amount'], 2);
          } else {
              continue;
          }

          $date = $n['date3'] ? date("M d, Y H:i", strtotime($n['date3'])) : '';
          $clickable = ($n['category'] === 'money' && $n['type'] === 'received' && !empty($n['product_id']));
        ?>

        <div class="notification-card" data-group="<?= htmlspecialchars($group) ?>">
          <?php if ($clickable): ?>
            <form method="POST">
              <input type="hidden" name="product_id" value="<?= htmlspecialchars($n['product_id']) ?>">
              <button type="submit" class="card-btn">
                <div class="notification-header">
                  <div class="logo-wrap">
                    <div class="logo-ring">
                      <img src="images/tohome/logo.png" alt="logo">
                    </div>
                    <div class="logo-dot"></div>
                  </div>
                  <div class="notification-title"><?= $title ?></div>
                </div>
                <div class="notification-body"><?= $body ?></div>
                <div class="divider"></div>
                <div class="notification-footer">
                  <div class="notification-date"><?= htmlspecialchars($date) ?></div>
                  <div class="view-link">
                    <span class="view-text">View</span>
                    <div class="arrow-icon"><i class="fas fa-chevron-right"></i></div>
                  </div>
                </div>
              </button>
            </form>
          <?php else: ?>
            <div class="notification-header">
              <div class="logo-wrap">
                <div class="logo-ring">
                  <img src="images/tohome/logo.png" alt="logo">
                </div>
                <div class="logo-dot"></div>
              </div>
              <div class="notification-title"><?= $title ?></div>
            </div>
            <div class="notification-body"><?= $body ?></div>
            <div class="divider"></div>
            <div class="notification-footer">
              <div class="notification-date"><?= htmlspecialchars($date) ?></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<script>
  // Check subscription status
  const HAS_SUBSCRIPTION = <?php echo $has_subscription ? 'true' : 'false'; ?>;

  // ---------- Subscription Dialog Functions ----------
  function dismissDialog() {
      window.location.href = "dashboard.php";
  }

  function upgradeAccount() {
      window.location.href = "plan.php";
  }

  document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.tab');
    const cards = document.querySelectorAll('.notification-card');

    function setActive(filter){
      tabs.forEach(t=>{
        if(t.dataset.filter===filter){t.classList.add('tab-active');t.classList.remove('tab-inactive');}
        else{t.classList.remove('tab-active');t.classList.add('tab-inactive');}
      });
      cards.forEach(c=>{
        c.style.display = (c.dataset.group===filter) ? 'block' : 'none';
      });
    }

    tabs.forEach(t=>{
      t.addEventListener('click', ()=> {
        if (HAS_SUBSCRIPTION) {
          setActive(t.dataset.filter);
        } else {
          document.getElementById("subscriptionDialog").style.display = "flex";
        }
      });
    });

    setActive('transactions');
  });
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
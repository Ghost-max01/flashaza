<?php
session_start();

// Check if user is logged in, otherwise redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}
// Include database configuration part
include 'config.php';
require_once 'schedule_file.php';

// Fetch user details from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE uid = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is blocked
if ($user['block'] == 1) {
    header('Location: ban.php');
    exit();
}

// Set timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

// Current time
$current_time = new DateTime('now');

// Check subscription
if (!empty($user['subscription_date'])) {
    $subscription_time = new DateTime($user['subscription_date']);

    if ($current_time > $subscription_time) {
        // Subscription has expired
        $update_stmt = $pdo->prepare("UPDATE users 
            SET subscription_date = now, plan = 'free', email_alert = 0 
            WHERE uid = :user_id");
        $update_stmt->execute(['user_id' => $user_id]);
        
        // Update local user data
        $user['subscription_date'] = null;
        $user['plan'] = 'free';
        $user['email_alert'] = 0;
    }
}

// Check if user has active subscription
$has_subscription = !empty($user['subscription_date']) && $current_time <= new DateTime($user['subscription_date']);
$is_free_user = ($user['plan'] === 'free');

// Fetch support link from price table
$stmt = $pdo->prepare("SELECT price FROM price WHERE type = 'support'");
$stmt->execute();
$support = $stmt->fetch(PDO::FETCH_ASSOC);
$support_link = $support['price'] ?? '#';

// Handle balance visibility toggle
$balance_visible = true;
if (isset($_COOKIE['balance_visible'])) {
    $balance_visible = $_COOKIE['balance_visible'] === 'true';
}

if (isset($_POST['toggle_balance'])) {
    $balance_visible = !$balance_visible;
    setcookie('balance_visible', $balance_visible ? 'true' : 'false', time() + (30 * 24 * 60 * 60), '/');
    header('Location: dashboard.php');
    exit();
}

// Format username for display
$display_name = "HI, " . strtoupper($user['name']);
if (strpos($user['name'], ' ') !== false) {
    $name_parts = explode(' ', $user['name'], 2);
    $display_name = "HI, " . strtoupper($name_parts[0]);
}

// Format balance for display
$balance_display = $balance_visible ? "₦" . number_format($user['balance'], 2) : "₦******";
$balance_icon = $balance_visible ? "images/dashboard/open.png" : "images/dashboard/hide.png";

// Fetch latest 2 transactions for the user
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM history
        WHERE uid = :uid
        ORDER BY id DESC
        LIMIT 2
    ");
    $stmt->execute(['uid' => $user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPay Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body class="<?php echo $has_subscription ? '' : 'no-context'; ?>">
    <div class="container">
        <!-- Header Section - Fixed at top -->
        <div class="header">
            <div class="profile-section" onclick="window.location.href='profile.php'">
                <div class="profile-image">
                    <div class="avatar">
                        <img src="<?php echo !empty($user['profile']) ? htmlspecialchars($user['profile']) : 'https://placehold.co/40x40/00B875/FFFFFF?text=' . substr($user['name'], 0, 1); ?>" alt="Profile">
                    </div>
                    <div class="tier-badge">
                        <img id="tier-image" src="images/dashboard/tier3.png" alt="Tier">
                    </div>
                </div>
                <div class="username"><?php echo $display_name; ?></div>
            </div>
            <div class="header-icons">
                <div class="animation-container" id="lottie-container" onclick="window.open('https://wa.me/2348164005416', '_blank')">
                    <!-- Lottie animation will be loaded here -->
                </div>
                <div class="header-icon" onclick="window.location.href='scan.php'">
                    <img src="images/dashboard/scan.png" alt="Scanner">
                </div>
                <div class="header-icon" onclick="window.location.href='notification.php'">
                    <img src="images/dashboard/notification.png" alt="Notifications">
                </div>
            </div>
        </div>
        
        <!-- Content Area - Scrollable -->
        <div class="content">
            <!-- Balance Dashboard -->
            <div class="biz"> 
                <!-- Balance Dashboard -->
                <div class="balance-dashboard">
                    <div class="balance-header">
                        <form method="POST" class="balance-toggle-form">
                            <button type="submit" name="toggle_balance" class="balance-title" style="background: none; border: none; cursor: pointer;">
                                <img src="images/dashboard/badge.png" alt="Verification">
                                <span>Available Balance</span>
                                <img src="<?php echo $balance_icon; ?>" alt="Toggle Balance">
                            </button>
                        </form>
                        <div class="history-link" onclick="window.location.href='transaction-history.php'">
                            <span>Transaction History</span>
                            <span class="material-icons">chevron_right</span>
                        </div>
                    </div>
                    <div class="balance-amount">
                        <div class="amount">
                            <span><?php echo $balance_display; ?></span>
                            <span class="material-icons">chevron_right</span>
                        </div>
                        <div class="add-money" onclick="window.location.href='add-money.php'">+ Add Money</div>
                    </div>
                </div>
                <!-- BizPayment Row -->  
                <div class="biz-row">  
                    <div class="biz-left">  
                        <img src="images/dashboard/biz.png" alt="">
                        <div class="biz-text">  
                            BizPayment: today received <span class="amountt">₦0.00</span>  
                        </div>  
                    </div>  
                    <div class="chevron">›</div>  
                </div>  
            </div>  

            <!-- Transaction List Section -->
            <div class="list-section" id="listSection">
                <!-- Transactions will be loaded here via JavaScript -->
            </div>
            
            <?php if ($is_free_user): ?>
<div onclick="window.location.href='plan.php'" style="
display:flex;
align-items:flex-start;
gap:12px;
margin:14px 15px;
padding:16px;
background:#fff6f6;
border-left:4px solid #d32f2f;
border-radius:12px;
color:#b00020;
cursor:pointer;
">

  <i class="fa-solid fa-triangle-exclamation fa-bounce" style="font-size:22px; margin-top:2px;"></i>

  <div style="flex:1; display:flex; flex-direction:column; font-size:13px;">
    <strong style="font-size:15px; margin-bottom:6px;">
      Upgrade Required
    </strong>

    <span style="opacity:.9; line-height:1.45;">
      Upgrade your account to unlock more features like adding funds to your dashboard,
      viewing full transaction history, sharing receipts, and more.
    </span>

    <span style="margin-top:8px; font-size:12px; opacity:.85;">
      Need help? Contact admin on WhatsApp:
      <strong>+2348164005416</strong>
    </span>
  </div>

  <i class="fa-solid fa-chevron-right" style="font-size:18px; opacity:.6; margin-top:6px;"></i>

</div>
<?php endif; ?>
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-item" onclick="window.location.href='to-opay.php'">
                    <div class="action-icon">
                        <img src="images/dashboard/toopay.png" alt="To Opay">
                    </div>
                    <div class="action-text">To Opay</div>
                </div>
                <div class="action-item" onclick="window.location.href='to-bnk.php'">
                    <div class="action-icon">
                        <img src="images/dashboard/toban.png" alt="To Bank">
                    </div>
                    <div class="action-text">To Bank</div>
                </div>
                <div class="action-item">
                    <div class="action-icon">
                        <img src="images/dashboard/withdraw.png" alt="Withdraw">
                    </div>
                    <div class="action-text">Withdraw</div>
                </div>
            </div>
            
            <!-- Services Grid -->
            <div class="services-grid">
                <div class="services-row" style="margin-bottom: 18px;">
                    <div class="service-item" onclick="window.location.href='airtime.php'">
                        <div class="service-icon">
                            <img src="images/dashboard/airtime.png" alt="Airtime" style="width: 60px; height: 60px; transform: translateX(7px);">
                        </div>
                        <div class="service-text">Airtime</div>
                    </div>
                    
                    <div class="service-item" onclick="window.location.href='data.php'">
                        <div class="service-icon">
                            <img src="images/dashboard/data.png" alt="Data" style="width: 60px; height: 60px; transform: translateX(7px);">
                        </div>
                        <div class="service-text">Data</div>
                    </div>
                    
                    <div class="service-item">
                        <div class="service-icon">
                            <img src="images/dashboard/betting.png" alt="Betting" style="width: 65px; height: 65px; transform: translateX(7px);">
                        </div>
                        <div class="service-text">Betting</div>
                    </div>
                    
                    <div class="service-item">
                        <div class="service-icon">
                            <img src="images/dashboard/tv.png" alt="TV">
                        </div>
                        <div class="service-text">TV</div>
                    </div>
                </div>
                <div class="services-row" style="margin-bottom: 6px;">
                    <div class="service-item">
                        <div class="service-icon">
                            <img src="images/dashboard/safebox.png" alt="Safebox">
                        </div>
                        <div class="service-text">Safebox</div>
                    </div>
                   
                    <div class="service-item">
                        <div class="service-icon">
                            <img src="images/dashboard/loan.png" alt="Loan">
                        </div>
                        <div class="service-text">Loan</div>
                    </div>
                    
                    <div class="service-item">
                        <div class="service-icon">
                            <img src="images/dashboard/check-in.png" alt="Invitation">
                        </div>
                        <div class="service-text">Check-in</div>
                    </div>
                   
                    <div class="service-item">
                        <div class="service-icon">
                            <img src="images/dashboard/more.png" alt="More">
                        </div>
                        <div class="service-text">More</div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Banner -->
            <div class="additional-banner">
                <!-- This banner uses a background image via CSS -->
            </div>
            
            <!-- Promotions Carousel -->
            <div class="promotions">
                <div class="carousel">
                    <div class="carousel-item">
                        <img src="images/dashboard/promo1.png" alt="Special Offer: Get 10% Cashback on Bills Payment">
                    </div>
                    <div class="carousel-item">
                        <img src="images/dashboard/promo2.png" alt="Refer Friends and Earn ₦1,000">
                    </div>
                    <div class="carousel-item">
                        <img src="images/dashboard/promo3.png" alt="Free Transfers This Weekend">
                    </div>
                </div>
                <div class="carousel-dots">
                    <div class="dot active" data-index="0"></div>
                    <div class="dot" data-index="1"></div>
                    <div class="dot" data-index="2"></div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Navigation - Fixed at bottom -->
        <div class="bottom-nav">
            <div class="nav-item active">
                <div class="nav-icon">
                    <img src="images/dashboard/home.png" alt="Home">
                </div>
                <div class="nav-text">Home</div>
            </div>
            <div class="nav-item">
                <div class="nav-icon">
                    <img src="images/dashboard/gold.png" alt="Rewards">
                </div>
                <div class="nav-text">Rewards</div>
            </div>
            <div class="nav-item">
                <div class="nav-icon">
                    <img src="images/dashboard/finance.png" alt="Finance">
                </div>
                <div class="nav-text">Finance</div>
            </div>
            <div class="nav-item">
                <div class="nav-icon">
                    <img src="images/dashboard/card.png" alt="Cards">
                </div>
                <div class="nav-text">Cards</div>
            </div>
            <div class="nav-item" onclick="window.location.href='home2.php'">
                <div class="nav-icon">
                    <img src="images/dashboard/me.png" alt="Me">
                </div>
                <div class="nav-text">Me</div>
            </div>
        </div>
    </div>

    <!-- Subscription Dialog -->
    <div class="subscription-dialog" id="subscriptionDialog">
        <div class="dialog-content">
            <div class="dialog-icon">🔒</div>
            <div class="dialog-title">Access Denied</div>
            <div class="dialog-message">You don't have an active subscription to access transaction details. Kindly upgrade your account to continue.</div>
            <div class="dialog-buttons">
                <button class="dialog-button button-dismiss" onclick="dismissDialog()">Dismiss</button>
                <button class="dialog-button button-upgrade" onclick="upgradeAccount()">Upgrade Account</button>
            </div>
        </div>
    </div>

    <!-- Popup menu -->
    <div id="popupMenu" class="hidden"></div>

   <script src="js/dashboard.js" defer></script>
   <script>
    const hasSubscription = <?php echo $has_subscription ? 'true' : 'false'; ?>;
    const transactionData = <?php echo json_encode($transactions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const emailAlert = <?php echo $user['email_alert'] ? 'true' : 'false'; ?>;
</script>
<script src="js/dashboard.js" defer></script>
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
<!-- JOIN WHATSAPP CHANNEL POPUP (FREE USERS) -->
<div id="joinChannelPopup" style="
display:none;
position:fixed;
top:0;
left:0;
right:0;
bottom:0;
background:rgba(0,0,0,0.6);
z-index:9999;
align-items:center;
justify-content:center;
">

  <div style="
  background:#fff;
  width:85%;
  max-width:360px;
  padding:30px;
  border-radius:12px;
  text-align:center;
  ">

    <i class="fa-brands fa-telegram" style="font-size:42px;color:#229ED9;"></i>

    <h3 style="margin:15px 0;">Join Our Telegram Channel</h3>

    <p style="font-size:16px;color:#555;">
     You must join our official Telegram channel for more updates and tools, tap on the join channel now to continue.
    </p>

    <div style="margin-top:15px;display:flex;gap:10px;">
      <button onclick="joinChannel()" style="
      flex:1;
      background:#229ED9;
      color:#fff;
      border:none;
      padding:20px;
      border-radius:10px;
      font-weight:600;
      ">
        Join Channel
      </button>

      <button onclick="closeJoinPopup()" style="
      flex:1;
      background:#eee;
      border:none;
      padding:12px;
      border-radius:8px;
      ">
        Later
      </button>
    </div>

  </div>
</div>
<script>
  const isFreeUser = <?php echo $is_free_user ? 'true' : 'false'; ?>;

  document.addEventListener("DOMContentLoaded", function () {
    if (isFreeUser) {
      setTimeout(function () {
        document.getElementById("joinChannelPopup").style.display = "flex";
      }, 1000);
    }
  });

  function joinChannel() {
    window.open(
      "https://t.me/web404_space",
      "_blank"
    );
  }

  function closeJoinPopup() {
    document.getElementById("joinChannelPopup").style.display = "none";
  }
</script>
</body>
</html>
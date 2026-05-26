<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}
include "config.php"; // provides $pdo

// Debug while testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uid = $_SESSION['user_id'];

/* ===========================
   Balance visibility (cookie)
   =========================== */
$balance_visible = true;
if (isset($_COOKIE['balance_visible'])) {
    $balance_visible = $_COOKIE['balance_visible'] === 'true';
}
if (isset($_POST['toggle_balance'])) {
    $balance_visible = !$balance_visible;
    setcookie('balance_visible', $balance_visible ? 'true' : 'false', time() + (30 * 24 * 60 * 60), '/');
    header('Location: home2.php');
    exit();
}

/* ===========================
   Fetch user row
   =========================== */
$stmt = $pdo->prepare("SELECT * FROM users WHERE uid = ? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* ===========================
   Name / Balance / Profile image
   =========================== */
$fullName  = $user['name'] ?? "USER";
$firstName = explode(" ", trim($fullName))[0];

$balance = isset($user['balance']) ? (float)$user['balance'] : 0.0;

// Profile image from user profile column (with safe fallbacks)
$profileRaw = $user['profile']
           ?? $user['profile_image']
           ?? $user['profile_img']
           ?? $user['avatar']
           ?? $user['photo']
           ?? $user['image']
           ?? "";

if ($profileRaw !== "") {
    $firstSplit   = preg_split('/[,\|]/', $profileRaw);
    $profileImage = trim($firstSplit[0]);
} else {
    $profileImage = "images/default_user.png"; // fallback
}

// Eye icon state
$eyeIcon = $balance_visible ? 'fa-eye' : 'fa-eye-slash';

/* ===========================
   Format Balance into naira + kobo
   =========================== */
if ($balance_visible) {
    $balanceFormatted = number_format($balance, 2);
    $parts = explode('.', $balanceFormatted);
    $naira = $parts[0];
    $kobo  = $parts[1];
} else {
    $naira = "****";
    $kobo  = "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- ✅ Prevent zooming, fit phone width, safe on iPhone -->
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title>OPay Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/home2.css">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="profile-section" onclick="location.href='profile.php'">
        <div class="profile-image">
          <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile">
        </div>
        <div class="profile-info">
          <div class="username">HI, <?= htmlspecialchars($firstName) ?></div>
          <div class="tier-badge">
            <img id="tier-badge-img" src="images/toban/tier3.png" alt="Tier">
          </div>
        </div>
        <div class="settings-wrapper" id="settings-btn">
          <div class="settings-icon"><img src="images/tohome/setting.png" alt="Settings"></div>
          <div id="lottie-coins"></div>
        </div>
      </div>

      <div class="balance-section">
        <div class="balance-info">
          <div class="balance-label">
            Total Balance
            <form method="POST" style="display:inline;">
              <button type="submit" name="toggle_balance" class="hide-btn">
                <div class="hide-icon"><i class="fas <?= $eyeIcon ?>"></i></div>
              </button>
            </form>
          </div>
          <div class="amount-display">
            <?php if ($balance_visible): ?>
              <div class="currency">₦</div>
            <?php endif; ?>
            <div class="amount">
              <span class="naira"><?= $naira ?></span>
              <?php if ($balance_visible): ?>
                <span class="kobo">.<?= $kobo ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="cashback-badge">
            <div class="cashback-text">& Cashback</div>
            <div class="cashback-amount">₦6.00</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Menu Section 1 -->
    <div class="menu-section">
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/history.png"></div><div class="menu-text">Transaction History</div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/speed.png"></div><div class="menu-text">Account Limits<div class="menu-description">View your transaction limits</div></div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/card.png"></div><div class="menu-text">Bank Card/Account<div class="menu-description">Add payment options</div></div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/shop.png"></div><div class="menu-text">Transfer to Me<div class="menu-description">Received payments for your business</div></div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
    </div>

    <!-- Menu Section 2 -->
    <div class="menu-section" style="margin-top: 15px;">
      <div class="menu-item"><div class="menu-icon"><img src="images/toban/badge.png"></div><div class="menu-text">Security Center<div class="menu-description">Protect your funds</div></div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/support.png"></div><div class="menu-text">Customer Service Center</div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/hurray.png"></div><div class="menu-text">Invitation<div class="menu-description">Invite friends and earn up to ₦4,200 Bonus</div></div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/call.png"></div><div class="menu-text">OPay USSD</div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
      <div class="menu-item"><div class="menu-icon"><img src="images/tohome/star.png"></div><div class="menu-text">Rate Us</div><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
      <div class="nav-item" onclick="location.href='dashboard.php'"><div class="nav-icon"><img src="images/tohome/home.png"></div><div class="nav-text">Home</div></div>
      <div class="nav-item"><div class="nav-icon"><img src="images/dashboard/gold.png"></div><div class="nav-text">Rewards</div></div>
      <div class="nav-item"><div class="nav-icon"><img src="images/dashboard/finance.png"></div><div class="nav-text">Finance</div></div>
      <div class="nav-item"><div class="nav-icon"><img src="images/dashboard/card.png"></div><div class="nav-text">Cards</div></div>
      <div class="nav-item active"><div class="nav-icon"><img src="images/tohome/me.png"></div><div class="nav-text">Me</div></div>
    </div>
  </div>

  <!-- Lottie library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.2/lottie.min.js"></script>
  <script>
      document.addEventListener('DOMContentLoaded', function () {
      function normalizeTier(t) {
        if (!t) return 'tier1';
        t = String(t).toLowerCase().trim();
        if (t === '1' || t === 'tier1') return 'tier1';
        if (t === '2' || t === 'tier2') return 'tier2';
        return 'tier3';
      }
      const saved = localStorage.getItem('tier');
      const tier = normalizeTier(saved);
      const badge = document.getElementById('tier-badge-img');
      if (tier === 'tier1') badge.src = 'images/tohome/upgrade-to-tier2.png';
      else if (tier === 'tier2') badge.src = 'images/tohome/upgrade-to-tier3.png';
      else badge.src = 'images/toban/tier3.png';

      document.getElementById('settings-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        window.location.href = 'setting.php';
      });

      lottie.loadAnimation({
        container: document.getElementById('lottie-coins'),
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: 'json/security.json'
      });
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
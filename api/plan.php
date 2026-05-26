<?php
session_start();

// if not logged in, redirect
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// load database connection
require_once "config.php"; // must return $pdo (PDO instance)

// fetch plan prices
$plans = ['weekly', 'monthly', 'lifetime'];
$prices = [];

try {
    $stmt = $pdo->prepare("SELECT type, price FROM price WHERE type IN (?, ?, ?)");
    $stmt->execute($plans);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $prices[strtolower($row['type'])] = $row['price'];
    }
} catch (PDOException $e) {
    die("Error loading plans: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Subscription Plans</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/plan.css">
</head>
<body>
  <!-- Header -->
  <div class="header">
    <a href="#" class="back-btn">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div class="title">Choose Your Plan</div>
  </div>

  <div class="container">
    <div class="plans-container">
      <!-- Weekly Plan -->
      <div class="plan weekly">
        <div class="plan-icon">
          <i class="fas fa-calendar-week"></i>
        </div>
        <h2>Weekly Plan</h2>
        <div class="price weekly">₦<?php echo isset($prices['weekly']) ? number_format($prices['weekly'], 2) : "0.00"; ?></div>
        <p>Perfect for short-term usage</p>
        <div class="divider"></div>
        <div class="features">
          <div class="feature"><i class="fas fa-check"></i><span>Basic feature access</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Weekly billing period</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>1GB database storage</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Automated backups</span></div>
        </div>
        <a href="pay.php?amount=<?php echo isset($prices['weekly']) ? $prices['weekly'] : 0; ?>&plan=week">
          <button class="select-btn">Select Weekly Plan</button>
        </a>
      </div>

      <!-- Monthly Plan -->
      <div class="plan monthly popular">
        <div class="popular-badge">POPULAR</div>
        <div class="plan-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <h2>Monthly Plan</h2>
        <div class="price monthly">₦<?php echo isset($prices['monthly']) ? number_format($prices['monthly'], 2) : "0.00"; ?></div>
        <p>Best value for regular users</p>
        <div class="divider"></div>
        <div class="features">
          <div class="feature"><i class="fas fa-check"></i><span>Push notifications</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Unlock receipt history</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>30 days billing period</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Multi-device sync</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Unlock status reporting</span></div>
        </div>
        <a href="pay.php?amount=<?php echo isset($prices['monthly']) ? $prices['monthly'] : 0; ?>&plan=month">
          <button class="select-btn">Select Monthly Plan</button>
        </a>
      </div>

      <!-- Lifetime Plan -->
      <div class="plan lifetime">
        <div class="plan-icon">
          <i class="fas fa-infinity"></i>
        </div>
        <h2>Lifetime Plan</h2>
        <div class="price lifetime">₦<?php echo isset($prices['lifetime']) ? number_format($prices['lifetime'], 2) : "0.00"; ?></div>
        <p>One-time payment, forever access</p>
        <div class="divider"></div>
        <div class="features">
          <div class="feature"><i class="fas fa-check"></i><span>Lifetime access</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Email notifications</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Sync across multiple devices</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>Automated cloud backups</span></div>
          <div class="feature"><i class="fas fa-check"></i><span>All features unlocked</span></div>
        </div>
        <a href="pay.php?amount=<?php echo isset($prices['lifetime']) ? $prices['lifetime'] : 0; ?>&plan=lifetime">
          <button class="select-btn">Select Lifetime Plan</button>
        </a>
      </div>
    </div>
    
    <!-- Removed the footer with refund text -->
  </div>
</body>
</html>
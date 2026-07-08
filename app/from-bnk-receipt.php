<?php
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userAgent = $_SERVER['HTTP_USER_AGENT'];
// ✅ Include database connection
include "config.php";

// ✅ Get product_id from URL
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    die("Invalid request.");
}
$product_id = $_GET['product_id'];

// ✅ Fetch transaction row
$stmt = $pdo->prepare("SELECT url, amount, accountname, status, bankname, accountnumber, narration, tid, date2, sid 
                       FROM history 
                       WHERE product_id = ? LIMIT 1");
$stmt->execute([$product_id]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$txn) {
    die("Transaction not found.");
}

// ✅ Mask account number (e.g. 123****890)
function maskAccount($acc) {
    $acc = (string)$acc;
    if (strlen($acc) >= 6) {
        return substr($acc, 0, 3) . "****" . substr($acc, -3);
    }
    return $acc;
}

$accountMasked = maskAccount($txn['accountnumber']);

// Remark: use narration if present, otherwise "Transfer from {accountname}:{masked}"
if (!empty(trim($txn['narration']))) {
    $remark = $txn['narration'];
} else {
    $remark = "Transfer from " . ($txn['accountname'] ?? '') . ": " . $accountMasked;
}

// Normalize status and prepare icon/text + footer visibility
$rawStatus = isset($txn['status']) ? trim(strtolower($txn['status'])) : '';
$footerVisible = true;

function normalizeBankName($name) {
    return strtolower(trim(preg_replace('/[^a-z0-9 ]+/', ' ', (string)$name)));
}

function getLocalBankLogo($bankName) {
    $name = normalizeBankName($bankName);
    if (strpos($name, 'opay') !== false) return '../images/toban/opay.png';
    if (strpos($name, 'access') !== false) return '../images/toban/access.png';
    if (strpos($name, 'first') !== false) return '../images/toban/first.png';
    if (strpos($name, 'guaranty') !== false || strpos($name, 'gtb') !== false) return '../images/toban/gt.png';
    if (strpos($name, 'united bank for africa') !== false || strpos($name, 'uba') !== false) return '../images/toban/uba.png';
    if (strpos($name, 'zenith') !== false) return '../images/toban/zenith.png';
    $slug = preg_replace('/[^a-z0-9]+/', '-', trim($name));
    if ($slug !== '') {
        $localPath = __DIR__ . "/../images/toban/{$slug}.png";
        if (file_exists($localPath)) {
            return "../images/toban/{$slug}.png";
        }
    }
    return null;
}

if ($rawStatus === 'failed' || $rawStatus === 'fail') {
    $statusIconHtml = '<i class="fas fa-times-circle" style="color:#F44336"></i>';
    $statusTextHtml = '<span style="color:#F44336">Failed</span>';
    $footerVisible = false;
} elseif ($rawStatus === 'reversed' || $rawStatus === 'reverse') {
    $statusIconHtml = '<i class="fas fa-clock" style="color:#FFC107"></i>';
    $statusTextHtml = '<span style="color:#FFC107">Reversed</span>';
    $footerVisible = false;
} else {
    // treat anything else as success (keeps original "Successful" behavior)
    $statusIconHtml = '<i class="fas fa-check-circle" style="color:#00B876"></i>';
    // If the DB contains 'success' or similar, show Successful; otherwise show capitalized DB value
    $statusDisplay = ($rawStatus === 'success' || $rawStatus === '') ? 'Successful' : ucfirst($rawStatus);
    $statusTextHtml = '<span style="color:#00B876">' . htmlspecialchars($statusDisplay) . '</span>';
    $footerVisible = true;
}

// profile image fallback (use local bank logo when available, otherwise txn url)
$profileImage = getLocalBankLogo($txn['bankname']) ?: (!empty($txn['url']) ? $txn['url'] : 'https://cdn-icons-png.flaticon.com/512/3498/3498370.png');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaction Details</title>
    
      <!-- Add Font Awesome for status icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">   <link rel="stylesheet" href="../css/from-bnk-receipt.css">
</head>
<body>
    <!-- Fixed Header -->
    <div class="header">
        <a href="javascript:history.back()" class="back-btn">‹</a>
        <div class="title">Transaction Details</div>
        <img src="../images/history/support.png" alt="" style="width: 30px; height: 30px; filter: invert(0);">
    </div>

    <!-- Scrollable Content -->
    <div class="content">
        <!-- Transaction Header Card -->
        <div class="card transaction-header" style="margin-top: 18px;">
            <div class="avatar">
                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Bank Logo" onerror="this.onerror=null; this.src='../images/history/logo.png';">
            </div>
            <div class="transfer-from">Transfer from <?php echo htmlspecialchars($txn['accountname']); ?></div>
            <div class="amount">₦<?php echo number_format($txn['amount'], 2); ?></div>
            <div class="status">
                <?php echo $statusIconHtml . ' ' . $statusTextHtml; ?>
            </div>
        </div>

        <!-- Transaction Details Card -->
        <div class="card">
            <div class="section-title">Transaction Details</div>

            <div class="detail-row">
                <div class="detail-label">Credited to</div>
                <div class="detail-value">
                    Available Balance
                    <span class="material-icons" style="color: grey;">chevron_right</span>
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Sender Details</div>
                <div class="detail-value" style="display: flex; flex-direction: column;">
                    <div><?php echo htmlspecialchars($txn['accountname']); ?></div>
                    <div><?php echo htmlspecialchars($txn['bankname']); ?> | <?php echo $accountMasked; ?></div>
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Remark</div>
                <div class="detail-value" style="max-width: 90%;"><?php echo htmlspecialchars($remark); ?></div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Transaction Type</div>
                <div class="detail-value">Bank Deposit</div>
            </div>

            <div class="detail-row">
                <div class="detail-label">Transaction No.</div>
                <div class="detail-value" style="max-width: 100%;">
                    <?php echo htmlspecialchars($txn['tid']); ?>
                    <img src="../images/history/copy.png" alt="">
                </div>
            </div>

            <div class="detail-row">
                <div class="detail-label" style="max-width: 100%;">Transaction Date</div>
                <div class="detail-value"><?php echo htmlspecialchars($txn['date2']); ?></div>
            </div>

            <div class="detail-row" style="border-bottom: none;">
                <div class="detail-label">Session ID</div>
                <div class="detail-value" style="max-width: 100%;">
                    <?php echo htmlspecialchars($txn['sid']); ?>
                    <img src="../images/history/copy.png" alt="">
                </div>
            </div>
        </div>
        <?php if ($footerVisible): ?>
         <!-- More Actions Section -->
        <div class="content-section" id="legSection">
            <div class="section-title">More Actions</div>

            <div class="action-row">
                <div class="action-label">Choose Category</div>
                <div class="action-value">Transfer</div>
               <span class="material-icons" style="color: grey;">chevron_right</span>
            </div>

          <center>  <span class="divider-text">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</span> </center>
            <div class="more-actions" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="action-item" onclick="transferBack()" style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <img src="../images/history/transfer.png" style="width: 20px; height: 20px;" alt="transfer">
                    <span style="font-weight: 500;">Transfer Back</span>
                </div>

                <div class="action-item" onclick="downloadFromBankReceipt()" style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: #00B876;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 16L7 11H10V4H14V11H17L12 16ZM5 18H19V20H5V18Z" fill="#00B876"/>
                    </svg>
                    <span style="font-weight: 500;">Download</span>
                </div>
            </div>
        </div>
    </div>
     <?php endif; ?>
    <!-- Fixed Footer -->
    <?php if ($footerVisible): ?>
    <div class="footer">
        <div class="share-btn" onclick="window.location.href='share-receipt.php?product_id=<?php echo urlencode($product_id); ?>'">Share Receipt</div>
    </div>
    <?php endif; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <script>
        function transferBack() {
            alert('Transfer back clicked');
        }
        function downloadFromBankReceipt() {
            const bodyCopy = document.body.cloneNode(true);
            // strip script tags and navigation items in PDF clone
            const headerLink = bodyCopy.querySelector('.header .back-btn');
            if (headerLink) headerLink.remove();
            const footer = bodyCopy.querySelector('.footer');
            if (footer) footer.remove();
            const leg = bodyCopy.querySelector('.content-section');
            if (leg) leg.remove();

            const options = {
                margin: 10,
                filename: 'receipt-<?php echo preg_replace("/[^A-Za-z0-9_-]/", "", $product_id); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
            };
            html2pdf().set(options).from(bodyCopy).save();
        }
        
        // Add dark mode class to body if device prefers dark mode
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('dark-mode');
        }
        
        // Listen for changes in theme preference
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            if (event.matches) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        });
    </script>
</body>
</html>
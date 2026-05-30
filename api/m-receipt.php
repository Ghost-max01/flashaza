<?php
if (session_status()===PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';

$transaction = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM history WHERE product_id = :product_id AND uid = :user_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if (!$transaction) {
    die("Transaction not found or you don't have permission to view it.");
}

/* ---------- helpers ---------- */
function normalizeBankName($name) {
    return strtolower(trim(preg_replace('/[^a-z0-9 ]+/', ' ', (string)$name)));
}

function getLocalBankLogo($bankName) {
    $name = normalizeBankName($bankName);
    if (strpos($name, 'opay')     !== false) return '../images/toban/opay.png';
    if (strpos($name, 'access')   !== false) return '../images/toban/access.png';
    if (strpos($name, 'first')    !== false) return '../images/toban/first.png';
    if (strpos($name, 'guaranty') !== false || strpos($name, 'gtb') !== false) return '../images/toban/gt.png';
    if (strpos($name, 'united bank for africa') !== false || strpos($name, 'uba') !== false) return '../images/toban/uba.png';
    if (strpos($name, 'zenith')   !== false) return '../images/toban/zenith.png';
    if (strpos($name, 'kuda')     !== false) return '../images/toban/kuda.png';
    if (strpos($name, 'palmpay')  !== false) return '../images/toban/palmpay.png';
    if (strpos($name, 'moniepoint') !== false) return '../images/toban/moniepoint.png';
    if (strpos($name, 'sterling')   !== false) return '../images/toban/sterling.png';
    if (strpos($name, 'wema')       !== false) return '../images/toban/wema.png';
    if (strpos($name, 'fidelity')   !== false) return '../images/toban/fidelity.png';
    if (strpos($name, 'polaris')    !== false) return '../images/toban/polaris.png';
    $slug = preg_replace('/[^a-z0-9]+/', '-', trim($name));
    if ($slug !== '') {
        $localPath = __DIR__ . "/../images/toban/{$slug}.png";
        if (file_exists($localPath)) return "../images/toban/{$slug}.png";
    }
    return null;
}

/* ---------- Resolve data ---------- */
$bankName   = $transaction['bankname']      ?? 'Bank';
$accountName= $transaction['accountname']   ?? '';
$accountNum = $transaction['accountnumber'] ?? '';
$amount     = floatval($transaction['amount'] ?? 0);
$txDate     = $transaction['date2']         ?? ($transaction['date1'] ?? '');
$txRef      = $transaction['sid']           ?? ($transaction['product_id'] ?? '');
$status     = strtolower($transaction['status'] ?? 'success');
$txType     = strtolower($transaction['type'] ?? 'sent');

// Sender = logged-in user (mask the account number if available)
$senderName = $_SESSION['username'] ?? ($_SESSION['name'] ?? 'You');
$senderAcc  = $_SESSION['account_number'] ?? '';
if ($senderAcc) {
    // Mask like: SMS24------876
    $senderDisplay = strtoupper(substr($senderName, 0, 3))
                   . substr($senderAcc, 0, 2)
                   . '------'
                   . substr($senderAcc, -3);
} else {
    $senderDisplay = strtoupper($senderName);
}

// Format date nicely: "Friday, May 24th, 2025 | 8:57 AM"
$dateFormatted = '';
if ($txDate) {
    $ts = strtotime($txDate);
    if ($ts) {
        $day     = date('l', $ts);
        $month   = date('F', $ts);
        $dayNum  = date('j', $ts);
        $suffix  = in_array(($dayNum % 10), [1]) && $dayNum != 11 ? 'st'
                 : (in_array(($dayNum % 10), [2]) && $dayNum != 12 ? 'nd'
                 : (in_array(($dayNum % 10), [3]) && $dayNum != 13 ? 'rd' : 'th'));
        $year    = date('Y', $ts);
        $time    = date('g:i A', $ts);
        $dateFormatted = "{$day}, {$month} {$dayNum}{$suffix}, {$year} | {$time}";
    }
}
if (!$dateFormatted) $dateFormatted = $txDate;

// Beneficiary institution (bank name in uppercase, clean)
$beneficiaryBank = strtoupper(preg_replace('/\s+/', ' ', trim($bankName)));

// Logo for the receipt
$logoResolved = getLocalBankLogo($bankName);
$bankLogo = $logoResolved ?: (!empty($transaction['url']) ? $transaction['url'] : '../images/history/logo.png');

// Direction badge
$isDebit  = ($txType === 'sent');
$badgeClass = $isDebit ? 'debit-badge' : 'credit-badge';
$badgeText  = $isDebit ? 'DEBIT' : 'CREDIT';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Receipt · Flashaza</title>
<link rel="stylesheet" href="../css/m-receipt.css"/>
</head>
<body>

<!-- Back header -->
<header class="page-header">
  <a class="back-btn" onclick="history.back()" id="m-back-btn">&#8592;</a>
  <span class="title">Transaction Receipt</span>
</header>

<div class="scroll-wrapper">

  <!-- ===== Receipt Card ===== -->
  <div class="receipt-card" id="m-receipt-card">

    <!-- Blue header with logo -->
    <div class="receipt-header">
      <div class="moniepoint-logo-wrap">
        <div class="moniepoint-logo-icon">M</div>
        <div class="moniepoint-logo-text">
          <span class="name">Moniepoint</span>
          <span class="tagline">MFB</span>
        </div>
      </div>
    </div>

    <!-- White body -->
    <div class="receipt-body">
      <div class="receipt-meta-row">
        <span class="<?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($badgeText) ?></span>
        <div class="m-icon">M</div>
      </div>
      <div class="receipt-amount">&#8358;<?= number_format($amount, 2) ?></div>
    </div>

    <!-- Details box -->
    <div class="details-box">

      <div class="detail-item">
        <div class="detail-label">Transaction Type</div>
        <div class="detail-value">
          <span class="txn-type-pill"><?= ($isDebit && ($transaction['category'] ?? '') === 'purchase') ? 'Purchase' : ($isDebit ? 'Transfer' : 'Deposit') ?></span>
        </div>
      </div>

      <hr class="detail-divider"/>

      <div class="detail-item">
        <div class="detail-label">Sender Name</div>
        <div class="detail-value mono"><?= htmlspecialchars($senderDisplay) ?></div>
      </div>

      <hr class="detail-divider"/>

      <div class="detail-item">
        <div class="detail-label">Beneficiary</div>
        <div class="detail-value mono"><?= htmlspecialchars($accountNum) ?></div>
      </div>

      <hr class="detail-divider"/>

      <div class="detail-item">
        <div class="detail-label">Beneficiary Institution</div>
        <div class="detail-value"><?= htmlspecialchars($beneficiaryBank) ?></div>
      </div>

      <hr class="detail-divider"/>

      <div class="detail-item">
        <div class="detail-label">Transaction Date</div>
        <div class="detail-value"><?= htmlspecialchars($dateFormatted) ?></div>
      </div>

      <hr class="detail-divider"/>

      <div class="detail-item">
        <div class="detail-label">Transaction Reference</div>
        <div class="detail-value mono"><?= htmlspecialchars($txRef) ?></div>
      </div>

    </div><!-- /.details-box -->
  </div><!-- /.receipt-card -->

  <!-- Action buttons -->
  <div class="receipt-actions">
    <button class="action-btn secondary" onclick="history.back()">Back</button>
    <button class="action-btn primary" onclick="shareReceipt()">Share</button>
  </div>

</div><!-- /.scroll-wrapper -->

<script>
function shareReceipt() {
  const card = document.getElementById('m-receipt-card');
  if (navigator.share) {
    navigator.share({
      title: 'Transaction Receipt',
      text: 'Transaction of ₦<?= number_format($amount, 2) ?> — Ref: <?= htmlspecialchars($txRef) ?>'
    }).catch(() => {});
  } else {
    alert('Copy ref: <?= htmlspecialchars($txRef) ?>');
  }
}
</script>
</body>
</html>

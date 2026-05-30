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
    // Mask like: 508124*********0876
    $senderDisplay = substr($senderAcc, 0, 6)
                   . '*********'
                   . substr($senderAcc, -4);
} else {
    $senderDisplay = strtoupper($senderName);
}

// Format date nicely: "Friday, May 29th, 2026 | 9:57 AM"
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

// Direction badge
$isDebit  = ($txType === 'sent');
$badgeClass = $isDebit ? 'badge-debit' : 'badge-credit';
$badgeText  = $isDebit ? 'Debit' : 'Credit';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Moniepoint Receipt</title>
  <link rel="stylesheet" href="../css/m-receipt.css"/>
</head>
<body>

  <!-- Optional top usability back button header -->
  <header class="page-header">
    <a class="back-btn" onclick="history.back()">&#8592;</a>
    <span class="title">Transaction Receipt</span>
  </header>

  <div class="receipt-wrapper">

    <!-- Header -->
    <div class="header">
      <div class="logo-icon">
        <span>M</span>
      </div>
      <div class="logo-text">
        <span class="brand">Moniepoint</span>
        <span class="sub">Microfinance Bank</span>
      </div>
    </div>

    <!-- White Card -->
    <div class="receipt-card">

      <div class="<?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars($badgeText) ?></div>

      <div class="amount-row">
        <div class="amount">₦<?= number_format($amount, 2) ?></div>
        <div class="m-icon"><span>M</span></div>
      </div>

      <div class="details">

        <div class="detail-row">
          <div class="detail-label">Transaction Type</div>
          <div class="detail-value">
            <span class="badge-purchase"><?= ($isDebit && ($transaction['category'] ?? '') === 'purchase') ? 'Purchase' : ($isDebit ? 'Transfer' : 'Deposit') ?></span>
          </div>
        </div>

        <div class="detail-row">
          <div class="detail-label">Sender Name</div>
          <div class="detail-value"><?= htmlspecialchars($senderDisplay) ?></div>
        </div>

        <div class="detail-row">
          <div class="detail-label">Beneficiary</div>
          <div class="detail-value"><?= htmlspecialchars($accountNum) ?></div>
        </div>

        <div class="detail-row">
          <div class="detail-label">Beneficiary Institution</div>
          <div class="detail-value"><?= htmlspecialchars($beneficiaryBank) ?></div>
        </div>

        <div class="detail-row">
          <div class="detail-label">Transaction Date</div>
          <div class="detail-value"><?= htmlspecialchars($dateFormatted) ?></div>
        </div>

        <div class="detail-row">
          <div class="detail-label">Transaction Reference</div>
          <div class="detail-value ref"><?= htmlspecialchars($txRef) ?></div>
        </div>

      </div>

    </div>

  </div>

  <!-- Share & Back action buttons underneath card -->
  <div class="receipt-actions">
    <button class="action-btn secondary" onclick="history.back()">Back</button>
    <button class="action-btn primary" onclick="shareReceipt()">Share</button>
  </div>

  <script>
  function shareReceipt() {
    if (navigator.share) {
      navigator.share({
        title: 'Transaction Receipt',
        text: 'Transaction of ₦<?= number_format($amount, 2) ?> — Ref: <?= htmlspecialchars($txRef) ?>'
      }).catch(() => {});
    } else {
      alert('Reference copied: <?= htmlspecialchars($txRef) ?>');
    }
  }
  </script>

</body>
</html>

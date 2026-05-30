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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #f0f0f0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    /* Outer phone-like wrapper */
    .receipt-wrapper {
      width: 390px;
      background: #2156F4;
      border-radius: 32px;
      padding: 40px 24px 50px;
      position: relative;
      overflow: hidden;
    }

    /* Diagonal gold ribbon — top-left */
    .receipt-wrapper::before {
      content: '';
      position: absolute;
      top: -30px;
      left: -40px;
      width: 200px;
      height: 340px;
      background: #C89A2A;
      transform: rotate(35deg);
      z-index: 0;
      opacity: 0.95;
    }

    /* Diagonal gold ribbon — bottom-right */
    .receipt-wrapper::after {
      content: '';
      position: absolute;
      bottom: -60px;
      right: -60px;
      width: 200px;
      height: 340px;
      background: #C89A2A;
      transform: rotate(35deg);
      z-index: 0;
      opacity: 0.95;
    }

    /* Header: logo row */
    .header {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin-bottom: 28px;
    }

    .logo-icon {
      width: 48px;
      height: 48px;
      background: #fff;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-icon span {
      font-size: 22px;
      font-weight: 900;
      color: #2156F4;
      letter-spacing: -1px;
    }

    .logo-text {
      display: flex;
      flex-direction: column;
    }

    .logo-text .brand {
      font-size: 22px;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.5px;
      line-height: 1;
    }

    .logo-text .sub {
      font-size: 9px;
      font-weight: 500;
      color: #fff;
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    /* White card */
    .receipt-card {
      position: relative;
      z-index: 1;
      background: #fff;
      border-radius: 20px;
      padding: 28px 24px 32px;
    }

    /* CREDIT badge */
    .badge-credit {
      display: inline-block;
      background: #DDE6FF;
      color: #2156F4;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      padding: 5px 12px;
      border-radius: 6px;
      margin-bottom: 10px;
    }

    /* DEBIT badge */
    .badge-debit {
      display: inline-block;
      background: #FFE2E2;
      color: #D62828;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      padding: 5px 12px;
      border-radius: 6px;
      margin-bottom: 10px;
    }

    /* Amount row */
    .amount-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 28px;
    }

    .amount {
      font-size: 42px;
      font-weight: 900;
      color: #0a0a0a;
      letter-spacing: -1.5px;
      line-height: 1;
    }

    .m-icon {
      width: 52px;
      height: 52px;
      background: #2156F4;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .m-icon span {
      font-size: 22px;
      font-weight: 900;
      color: #fff;
    }

    /* Details block */
    .details {
      background: #F0F3FB;
      border-radius: 14px;
      padding: 20px 18px;
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .detail-row {
      padding: 14px 0;
      border-bottom: 1px solid #E2E8F5;
    }

    .detail-row:first-child {
      padding-top: 0;
    }

    .detail-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .detail-label {
      font-size: 12.5px;
      font-weight: 400;
      color: #9aa3ba;
      margin-bottom: 6px;
      letter-spacing: 0.1px;
    }

    .detail-value {
      font-size: 15px;
      font-weight: 500;
      color: #0d0d0d;
      line-height: 1.4;
      letter-spacing: 0.1px;
    }

    /* Purchase badge inside details */
    .badge-purchase {
      display: inline-block;
      background: #DDE6FF;
      color: #2156F4;
      font-size: 13px;
      font-weight: 700;
      padding: 5px 14px;
      border-radius: 7px;
    }

    .detail-value.ref {
      font-size: 13.5px;
      word-break: break-all;
    }

    /* Receipt actions */
    .receipt-actions {
      display: flex;
      gap: 12px;
      margin-top: 28px;
      justify-content: center;
    }

    .action-btn {
      padding: 12px 28px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .action-btn.primary {
      background: #2156F4;
      color: #fff;
    }

    .action-btn.primary:hover {
      background: #1a45d4;
    }

    .action-btn.secondary {
      background: #f0f0f0;
      color: #333;
      border: 1px solid #ddd;
    }

    .action-btn.secondary:hover {
      background: #e0e0e0;
    }
  </style>
</head>
<body>

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
    <button class="action-btn primary" onclick="downloadReceipt()">Download</button>
    <button class="action-btn primary" onclick="shareReceipt()">Share</button>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
  <script>
  function shareReceipt() {
    if (navigator.share) {
      navigator.share({
        title: 'Transaction Receipt',
        text: 'Transaction of ₦<?= number_format($amount, 2) ?> — Ref: <?= htmlspecialchars($txRef) ?>'
      }).catch(() => {});
    } else {
      alert('Reference copied: <?= htmlspecialchars($txRef) ?>');

      function downloadReceipt() {
        const wrapper = document.querySelector('.receipt-wrapper');
        if (!wrapper) return;
        const clone = wrapper.cloneNode(true);
        const options = {
          margin: 10,
          filename: 'moniepoint-receipt-<?= preg_replace("/[^A-Za-z0-9_-]/", "", ($txRef ?: $product_id)) ?>.pdf',
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2 },
          jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
        };
        html2pdf().set(options).from(clone).save();
      }
    }
  }
  </script>

</body>
</html>

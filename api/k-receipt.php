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
$description = $transaction['description'] ?? ($transaction['narration'] ?? '');
$transferFee = $transaction['fee'] ?? 0;
$vat = $transaction['vat'] ?? 0;

// Sender = logged-in user
$senderName = $_SESSION['username'] ?? ($_SESSION['name'] ?? 'You');
$senderBank = 'Kuda';
$senderAcc  = $_SESSION['account_number'] ?? '';

// Format date nicely: "Apr 20, 2026" with time "5:35 PM"
$dateFormatted = '';
$timeFormatted = '';
if ($txDate) {
    $ts = strtotime($txDate);
    if ($ts) {
        $dateFormatted = date('M j, Y', $ts);
        $timeFormatted = date('g:i A', $ts);
    }
}
if (!$dateFormatted) {
    $dateFormatted = $txDate;
    $timeFormatted = '';
}

// Beneficiary institution (bank name in uppercase, clean)
$beneficiaryBank = strtoupper(preg_replace('/\s+/', ' ', trim($bankName)));

// Payment type label
$paymentType = $txType === 'sent' ? 'Outward Transfer' : ($txType === 'received' ? 'Inward Transfer' : 'Transfer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kuda Transaction Receipt</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: #f0f0f0;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 40px 16px;
      font-family: 'Inter', sans-serif;
    }

    .receipt {
      background: #ffffff;
      width: 100%;
      max-width: 620px;
      padding: 48px 48px 36px 48px;
    }

    /* ── Header ── */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 48px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Kuda "K" icon — two thick purple diagonal bars */
    .logo-icon {
      width: 36px;
      height: 36px;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-icon svg {
      width: 36px;
      height: 36px;
    }

    .logo-text {
      font-size: 28px;
      font-weight: 700;
      color: #1a1a1a;
      letter-spacing: -0.5px;
    }

    .header-title {
      font-size: 22px;
      font-weight: 400;
      color: #1a1a1a;
      letter-spacing: -0.3px;
    }

    /* ── Amount block ── */
    .amount-section {
      text-align: center;
      margin-bottom: 40px;
    }

    .amount-label {
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 10px;
      letter-spacing: 0;
    }

    .amount-value {
      font-size: 44px;
      font-weight: 700;
      color: #1a1a1a;
      letter-spacing: -1px;
    }

    /* ── Row table ── */
    .details-table {
      width: 100%;
      border-collapse: collapse;
    }

    .details-table tr {
      border-bottom: 1px solid #e8e8e8;
    }

    .details-table tr:first-child {
      border-top: 1px solid #e8e8e8;
    }

    .details-table td {
      padding: 16px 0;
      vertical-align: top;
    }

    .td-label {
      font-size: 13px;
      font-weight: 400;
      color: #9a9a9a;
      width: 45%;
      padding-right: 16px;
    }

    .td-value {
      font-size: 13px;
      font-weight: 600;
      color: #1a1a1a;
      text-align: right;
    }

    .td-value .sub {
      display: block;
      font-size: 11.5px;
      font-weight: 400;
      color: #6b6b6b;
      margin-top: 2px;
    }

    .td-value.ref {
      font-size: 10.5px;
      font-weight: 500;
      word-break: break-all;
      color: #1a1a1a;
    }

    /* ── Promo banner ── */
    .promo-banner {
      background: #ede8fb;
      border-radius: 14px;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 18px 20px;
      margin-top: 36px;
      margin-bottom: 36px;
    }

    .promo-icon {
      width: 44px;
      height: 44px;
      background: #6c3cba;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .promo-icon svg {
      width: 26px;
      height: 26px;
    }

    .promo-text {
      font-size: 13px;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1.45;
    }

    /* ── Footer ── */
    .footer {
      text-align: center;
      font-size: 10px;
      color: #9a9a9a;
      line-height: 1.7;
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
      background: #6c3cba;
      color: #fff;
    }

    .action-btn.primary:hover {
      background: #5a2d95;
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
  <div class="receipt">

    <!-- Header -->
    <div class="header">
      <div class="logo">
        <!-- Kuda logo: stylised K mark + wordmark -->
        <div class="logo-icon">
          <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Left vertical bar of K -->
            <rect x="4" y="4" width="6" height="28" rx="2" fill="#6c3cba"/>
            <!-- Top-right diagonal arm -->
            <rect x="9" y="4" width="18" height="7" rx="2" transform="rotate(0 9 4)" fill="#6c3cba"/>
            <line x1="10" y1="10" x2="28" y2="4" stroke="#6c3cba" stroke-width="7" stroke-linecap="round"/>
            <!-- Bottom-right diagonal arm -->
            <line x1="10" y1="22" x2="28" y2="32" stroke="#6c3cba" stroke-width="7" stroke-linecap="round"/>
            <!-- Middle join -->
            <rect x="4" y="15" width="14" height="6" rx="1" fill="#6c3cba"/>
          </svg>
        </div>
        <span class="logo-text">kuda.</span>
      </div>
      <span class="header-title">Transaction Details</span>
    </div>

    <!-- Amount -->
    <div class="amount-section">
      <div class="amount-label">Transaction Amount</div>
      <div class="amount-value">₦<?= number_format($amount, 2) ?></div>
    </div>

    <!-- Details rows -->
    <table class="details-table">
      <tr>
        <td class="td-label">Beneficiary Details</td>
        <td class="td-value">
          <?= htmlspecialchars($accountName) ?>
          <span class="sub"><?= htmlspecialchars($beneficiaryBank) ?> | <?= htmlspecialchars($accountNum) ?></span>
        </td>
      </tr>
      <tr>
        <td class="td-label">Sender Details</td>
        <td class="td-value">
          <?= htmlspecialchars($senderName) ?>
          <span class="sub"><?= htmlspecialchars($senderBank) ?> | <?= htmlspecialchars($senderAcc) ?></span>
        </td>
      </tr>
      <tr>
        <td class="td-label">Paid On</td>
        <td class="td-value">
          <?= htmlspecialchars($dateFormatted) ?>
          <span class="sub"><?= htmlspecialchars($timeFormatted) ?></span>
        </td>
      </tr>
      <tr>
        <td class="td-label">Transfer Fee</td>
        <td class="td-value">₦<?= number_format(floatval($transferFee), 2) ?></td>
      </tr>
      <tr>
        <td class="td-label">VAT</td>
        <td class="td-value">₦<?= number_format(floatval($vat), 2) ?></td>
      </tr>
      <tr>
        <td class="td-label">Description</td>
        <td class="td-value"><?= htmlspecialchars($description ?: 'N/A') ?></td>
      </tr>
      <tr>
        <td class="td-label">Transaction Reference</td>
        <td class="td-value ref"><?= htmlspecialchars($txRef) ?></td>
      </tr>
      <tr>
        <td class="td-label">Payment Type</td>
        <td class="td-value"><?= htmlspecialchars($paymentType) ?></td>
      </tr>
    </table>

    <!-- Promo banner -->
    <div class="promo-banner">
      <div class="promo-icon">
        <!-- Mini Kuda K in white -->
        <svg viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="5" height="20" rx="1.5" fill="white"/>
          <line x1="7" y1="7" x2="22" y2="3" stroke="white" stroke-width="5" stroke-linecap="round"/>
          <line x1="7" y1="16" x2="22" y2="23" stroke="white" stroke-width="5" stroke-linecap="round"/>
          <rect x="3" y="11" width="10" height="4" rx="1" fill="white"/>
        </svg>
      </div>
      <div class="promo-text">Not on Kuda? Tap here to<br>download the money app for<br>Africans</div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>© 2026 Kuda Technologies Ltd (Company No. 11472232). All rights reserved.</p>
      <p>If you would like to find out more about which Kuda entity you receive services from, please reach<br>out to us via the in-app chat in the Kuda app.</p>
      <p>Nigerian banking services offered by Kuda Microfinance Bank (RC796975) with registered address<br>at 1-11 Commercial avenue, Yaba, Lagos, Nigeria.. Kuda Microfinance Bank is licensed by the Central<br>Bank of Nigeria. Deposits are insured by the Nigerian Deposit Insurance Corporation (NDIC).</p>
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

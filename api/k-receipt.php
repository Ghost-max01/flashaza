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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kuda Transaction Receipt</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #f0f0f0;
      font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 24px 16px;
    }

    .receipt-wrapper {
      width: 100%;
      max-width: 480px;
      background: #ffffff;
      border-radius: 0px;
      overflow: hidden;
    }

    /* ── HEADER ── */
    .receipt-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 28px 28px 20px 28px;
    }

    .kuda-logo {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .kuda-logo .logo-icon {
      width: 36px;
      height: 36px;
    }

    .kuda-logo .logo-text {
      font-size: 26px;
      font-weight: 700;
      color: #1a1a1a;
      letter-spacing: -0.5px;
      line-height: 1;
    }

    .receipt-title {
      font-size: 20px;
      font-weight: 400;
      color: #1a1a1a;
      letter-spacing: 0;
    }

    /* ── AMOUNT BLOCK ── */
    .amount-block {
      text-align: center;
      padding: 24px 28px 32px 28px;
    }

    .amount-label {
      font-size: 14px;
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 10px;
      letter-spacing: 0;
    }

    .amount-value {
      font-size: 42px;
      font-weight: 700;
      color: #1a1a1a;
      letter-spacing: -1px;
      line-height: 1.1;
    }

    /* ── DETAILS TABLE ── */
    .details-table {
      width: 100%;
      border-collapse: collapse;
      padding: 0 28px;
      display: block;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 16px 28px;
      border-top: 1px solid #e8e8e8;
      gap: 16px;
    }

    .detail-row:last-child {
      border-bottom: 1px solid #e8e8e8;
    }

    .detail-label {
      font-size: 14px;
      color: #999999;
      font-weight: 400;
      white-space: nowrap;
      flex-shrink: 0;
      min-width: 130px;
      padding-top: 2px;
    }

    .detail-value {
      font-size: 14px;
      color: #1a1a1a;
      font-weight: 500;
      text-align: right;
      line-height: 1.5;
    }

    .detail-value .sub-value {
      display: block;
      font-size: 13px;
      color: #666666;
      font-weight: 400;
      margin-top: 2px;
    }

    .detail-value.bold {
      font-weight: 700;
    }

    .detail-value.reference {
      font-size: 12px;
      word-break: break-all;
      font-weight: 500;
    }

    /* ── BANNER ── */
    .kuda-banner {
      margin: 28px 28px 0 28px;
      background: #f0eeff;
      border-radius: 14px;
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .banner-icon {
      width: 52px;
      height: 52px;
      background: #4b0082;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .banner-icon svg {
      width: 28px;
      height: 28px;
    }

    .banner-text {
      font-size: 14.5px;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1.45;
    }

    /* ── FOOTER ── */
    .receipt-footer {
      text-align: center;
      padding: 24px 28px 32px 28px;
      margin-top: 24px;
    }

    .receipt-footer p {
      font-size: 11px;
      color: #999999;
      line-height: 1.65;
      font-weight: 400;
    }

    /* ── DOWNLOAD BUTTON ── */
    .download-container {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-top: 24px;
    }

    .download-btn {
      padding: 8px 20px;
      border: none;
      border-radius: 8px;
      background: #4b0082;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
    }

    .download-btn:hover {
      background: #3d0063;
      transform: translateY(-1px);
    }

    .download-btn:active {
      transform: translateY(0);
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 520px) {
      body {
        padding: 0;
        background: #fff;
      }

      .receipt-wrapper {
        max-width: 100%;
        border-radius: 0;
      }

      .receipt-header {
        padding: 22px 20px 16px 20px;
      }

      .amount-block {
        padding: 20px 20px 28px 20px;
      }

      .amount-value {
        font-size: 36px;
      }

      .detail-row {
        padding: 14px 20px;
      }

      .detail-label {
        min-width: 110px;
        font-size: 13px;
      }

      .detail-value {
        font-size: 13px;
      }

      .kuda-banner {
        margin: 20px 20px 0 20px;
      }

      .receipt-footer {
        padding: 20px 20px 28px 20px;
      }

      .receipt-title {
        font-size: 17px;
      }
    }

    @media (max-width: 360px) {
      .amount-value {
        font-size: 30px;
      }

      .kuda-logo .logo-text {
        font-size: 22px;
      }

      .detail-label {
        min-width: 95px;
        font-size: 12px;
      }

      .detail-value {
        font-size: 12px;
      }

      .detail-value.reference {
        font-size: 10.5px;
      }
    }
  </style>
</head>
<body>
  <div class="receipt-wrapper">

    <!-- HEADER -->
    <div class="receipt-header">
      <div class="kuda-logo">
        <!-- Kuda K icon -->
        <svg class="logo-icon" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="36" height="36" rx="4" fill="white"/>
          <path d="M10 8H15V17L22 8H28L20 18.5L28.5 28H22.5L15 19V28H10V8Z" fill="#4b0082"/>
        </svg>
        <span class="logo-text">kuda.</span>
      </div>
      <span class="receipt-title">Transaction Details</span>
    </div>

    <!-- AMOUNT -->
    <div class="amount-block">
      <div class="amount-label">Transaction Amount</div>
      <div class="amount-value">₦<?= number_format($amount, 2) ?></div>
    </div>

    <!-- DETAIL ROWS -->
    <div class="details-table">
      <div class="detail-row">
        <span class="detail-label">Beneficiary Details</span>
        <span class="detail-value bold">
          <?= htmlspecialchars($accountName ?: 'N/A') ?>
          <span class="sub-value"><?= htmlspecialchars($beneficiaryBank) ?> | <?= htmlspecialchars($accountNum) ?></span>
        </span>
      </div>

      <div class="detail-row">
        <span class="detail-label">Sender Details</span>
        <span class="detail-value bold">
          <?= htmlspecialchars($senderName) ?>
          <span class="sub-value"><?= htmlspecialchars($senderBank) ?> | <?= htmlspecialchars($senderAcc) ?></span>
        </span>
      </div>

      <div class="detail-row">
        <span class="detail-label">Paid On</span>
        <span class="detail-value bold">
          <?= htmlspecialchars($dateFormatted) ?>
          <span class="sub-value"><?= htmlspecialchars($timeFormatted) ?></span>
        </span>
      </div>

      <div class="detail-row">
        <span class="detail-label">Transfer Fee</span>
        <span class="detail-value bold">₦<?= number_format(floatval($transferFee), 2) ?></span>
      </div>

      <div class="detail-row">
        <span class="detail-label">VAT</span>
        <span class="detail-value bold">₦<?= number_format(floatval($vat), 2) ?></span>
      </div>

      <div class="detail-row">
        <span class="detail-label">Description</span>
        <span class="detail-value bold"><?= htmlspecialchars($description ?: 'N/A') ?></span>
      </div>

      <div class="detail-row">
        <span class="detail-label">Transaction Reference</span>
        <span class="detail-value reference"><?= htmlspecialchars($txRef) ?></span>
      </div>

      <div class="detail-row">
        <span class="detail-label">Payment Type</span>
        <span class="detail-value bold"><?= htmlspecialchars($paymentType) ?></span>
      </div>
    </div>

    <!-- BANNER -->
    <div class="kuda-banner">
      <div class="banner-icon">
        <!-- White K icon inside purple box -->
        <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M7 5H11.5V13.5L17.5 5H23L16 14.5L23.5 23H17.5L11.5 14.5V23H7V5Z" fill="white"/>
        </svg>
      </div>
      <div class="banner-text">Not on Kuda? Tap here to<br>download the money app for<br>Africans</div>
    </div>

    <!-- FOOTER -->
    <div class="receipt-footer">
      <p>© 2026 Kuda Technologies Ltd (Company No. 11472232). All rights reserved.<br>
      If you would like to find out more about which Kuda entity you receive services from, please reach<br>
      out to us via the in-app chat in the Kuda app.<br>
      Nigerian banking services offered by Kuda Microfinance Bank (RC796975) with registered address<br>
      at 1-11 Commercial avenue, Yaba, Lagos, Nigeria.. Kuda Microfinance Bank is licensed by the Central<br>
      Bank of Nigeria. Deposits are insured by the Nigerian Deposit Insurance Corporation (NDIC).</p>
    </div>

  </div>

  <!-- Download Button -->
  <div class="download-container">
    <button class="download-btn" onclick="downloadReceipt()">
      ⬇ Download Receipt
    </button>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    function downloadReceipt() {
      const receiptWrapper = document.querySelector('.receipt-wrapper');
      const fileName = 'Kuda_Receipt_<?= htmlspecialchars($txRef) ?>.pdf';
      
      const options = {
        margin: 10,
        filename: fileName,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
      };

      html2pdf().set(options).from(receiptWrapper).save();
    }
  </script>

</body>
</html>

<?php
session_start();

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}
// Include database configuration
require_once 'config.php';

// Get product_id from URL (keep it alphanumeric/string)
$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';

// Fetch transaction details from database
$transaction = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM history WHERE product_id = :product_id AND uid = :user_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_STR); // <-- STRING instead of INT
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle database error
    die("Database error: " . $e->getMessage());
}

// If transaction not found, redirect or show error
if (!$transaction) {
    die("Transaction not found or you don't have permission to view it.");
}

// Prepare data for JavaScript
$js_transaction_data = json_encode([
    'id' => $transaction['tid'],
    'amount' => floatval($transaction['amount']),
    'fee' => floatval($transaction['fee']),
    'amountPaid' => floatval($transaction['amount']),
    'recipientName' => $transaction['accountname'],
    'recipientDetails' => [
        'name' => $transaction['accountname'] ?? 'WEB TECH',
        'bank' => $transaction['bankname'] ?? 'United Bank For Africa',
        'account' => $transaction['accountnumber'] ?? '915****789'
    ],
    'transactionDate' => date('M jS, Y H:i:s', strtotime($transaction['date2'])),
    'sessionId' => $transaction['sid'],
    'status' => $transaction['status'], // "success", "failed", or "pending"
    'timeline' => [
        'payment' => $transaction['time1'] ?? 'WEB TECH',
        'processing' => $transaction['time1'] ?? 'WEB TECH',
        'received' => $transaction['time3'] ?? 'WEB TECH',
    ],
    'profileImage' => $transaction['url'] ?? 'images/history/logo.png'
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">  
  <meta name="viewport" content="width=device-width, initial-scale=1.0">  
  <title>Transaction Details</title>  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">  
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="css/bnk_receipt.css?v=1.0">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="back-arrow">‹</div>
      <div class="header-title">Transaction Details</div>
      <img src="images/history/support.png" alt="menu" class="menu-icon" />
    </div>

    
    <!-- Main Content -->
    <div class="scroll-view">
      <div class="card" style="margin-top: 20px;">
        <div class="profile-image"><img id="profileImage" src="" alt=""></div>
        <div class="amount-section">
          <div class="transfer-text" id="transferText">Transfer to </div>
          <div class="amount" id="amount">₦0.00</div>
          <div class="status" id="status">Status</div>
        </div>
        <center>
          <div class="timeline">
            <img src="images/history/tick.png" alt="dot" class="timeline-dot" />
            <div class="timeline-line" id="timelineLine1"></div>
            <img src="images/history/tick.png" alt="dot" class="timeline-dot" />
            <div class="timeline-line" id="timelineLine2"></div>
            <img src="images/history/tick.png" alt="dot" class="timeline-dot" />
          </div>
        </center>
        <div class="timeline-labels">
          <div class="timeline-label">
            <div class="label-title">Payment<br />Successful</div>
            <div class="label-time" id="paymentTime">00-00 00:00:00</div>
          </div>
          <div class="timeline-label">
            <div class="label-title">Processing<br/>by bank</div>
            <div class="label-time" id="processingTime">00-00 00:00:00</div>
          </div>
          <div class="timeline-label">
            <div class="label-title">Received<br />by bank</div>
            <div class="label-time" id="receivedTime">00-00 00:00:00</div>
          </div>
        </div>
        
        <div class="wrap" role="note" aria-label="Bank credit notice">
          <div class="bubble">
            
              The recipient account is expected to be credited within 5 minutes, subject to notification by the bank. If you have any questions, you can also
              <a href="#" onclick="return false;">contact the recipient bank</a>
              <span class="chev">&gt;&gt;</span>
              <i class="fas fa-phone phone-icon" aria-hidden="true"></i>
            
          </div>
        </div>
       

        <div class="detail-row">
          <div class="detail-label">Amount</div>
          <div class="detail-value" id="displayAmount">₦0.00</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Fee</div>
          <div class="detail-value" id="feeDisplay"><span style="color: #D3D3D3 ; text-decoration: line-through; margin-right: 3px;">₦0.00</span>₦0.00</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Amount Paid</div>
          <div class="detail-value" id="amountPaid">₦0.00</div>
        </div>
      </div>

      <div class="card">
        <div class="section-title">Transaction Details</div>
        <div class="detail-row">
          <div class="detail-label">Recipient Details</div>
          <div class="detail-value" id="recipientDetails" style="line-height:1.4; text-align:right;">
            WEB TECH<br>
            <span style="font-size:12px; color:#616161;">United Bank For Africa | 915****789</span>
          </div>
        </div>

        <div class="detail-row">
          <div class="detail-label">Transaction No.</div>
          <div class="detail-value" id="transactionId">
            250225010100418357292729 <img style="width:13px; height:13px;" src="images/history/copy.png" alt="">
          </div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Payment Method</div>
          <div class="detail-value">Wallet <i class="fas fa-angle-right"></i></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Transaction Date</div>
          <div class="detail-value" id="transactionDate">Feb 25th, 2025 08:19:56</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Session ID</div>
          <div class="detail-value" id="sessionId">
            100004250223182413127782423243
            <img style="width:13px; height:13px;" src="images/history/copy.png" alt="">
          </div>
        </div>
      </div>

      <!-- More Actions Section -->
      <div class="content-section" id="legSection">
        <div class="section-title">More Actions</div>
        <div class="action-row">
          <div class="action-label">Choose Category</div>
          <div class="action-value">Transfer</div>
          <span class="material-icons" style="color: grey;">chevron_right</span>
        </div>
        <center>
          <span class="divider-text">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</span>
        </center>
        <div class="more-actions">
          <div class="action-item" onclick="transferBack()">
            <img src="images/history/transfer.png" style="width: 20px; height: 20px;" alt="transfer">
            <span>Transfer Back</span>
          </div>
          <div class="action-item" onclick="viewRecords()"><span></span></div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <div class="action-buttons">
        <div class="button button-outline">Report an issue</div>
        <a href="share-receipt.php?product_id=<?php echo $product_id; ?>" class="button button-solid">Share Receipt</a>
      </div>
      <div class="dispute-button" id="disputeButton">
        <div class="dispute">Dispute</div>
      </div>
    </div>
  </div>

  <script>
    // Transaction data from PHP
    let transactionData = <?php echo $js_transaction_data; ?>;

    // Function to format currency with two decimal places
    function formatCurrency(amount) {
      return '₦' + amount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    // Function to update all receipt details
    function updateReceiptDetails() {
      // Update profile image
      document.getElementById('profileImage').src = transactionData.profileImage;
      
      // Update transaction details with proper currency formatting
      document.querySelector('.amount').textContent = formatCurrency(transactionData.amount);
      document.querySelector('.transfer-text').textContent = `Transfer to ${transactionData.recipientName}`;
      document.getElementById('displayAmount').textContent = formatCurrency(transactionData.amountPaid);
      document.getElementById('feeDisplay').innerHTML = `<span style="color: #D3D3D3 ; text-decoration: line-through; margin-right: 3px;">${formatCurrency(transactionData.fee)}</span>${formatCurrency(transactionData.fee)}`;
      document.getElementById('amountPaid').textContent = formatCurrency(transactionData.amount + 10);
      
      // Update recipient details
      const recipientElement = document.getElementById('recipientDetails');
      recipientElement.innerHTML = `${transactionData.recipientDetails.name}<br><span style="font-size:12px; color:#616161;">${transactionData.recipientDetails.bank} | ${transactionData.recipientDetails.account}</span>`;
      
      // Update other details
      document.getElementById('transactionId').innerHTML = `${transactionData.id} <img style="width:13px; height:13px;" src="images/history/copy.png" alt="">`;
      document.getElementById('transactionDate').textContent = transactionData.transactionDate;
      document.getElementById('sessionId').innerHTML = `${transactionData.sessionId} <img style="width:13px; height:13px;" src="images/history/copy.png" alt="">`;
      
      // Update timeline
      document.getElementById('paymentTime').textContent = transactionData.timeline.payment;
      document.getElementById('processingTime').textContent = transactionData.timeline.processing;
      document.getElementById('receivedTime').textContent = transactionData.timeline.received;
      
      // Update status
      updateUIByStatus();
    }

    // Function to update UI based on transaction status
    function updateUIByStatus() {
      const statusElement = document.getElementById('status');
      const timelineSection = document.querySelector('.timeline').parentElement;
      const timelineLabels = document.querySelector('.timeline-labels');
      const moreActionsSection = document.getElementById('legSection');
      const actionButtons = document.querySelector('.action-buttons');
      const disputeButton = document.getElementById('disputeButton');
      const bubbleSection = document.querySelector('.wrap');
      const timelineDots = document.querySelectorAll('.timeline-dot');
      const timelineLine1 = document.getElementById('timelineLine1');
      const timelineLine2 = document.getElementById('timelineLine2');
      
      // Reset classes
      statusElement.className = 'status';
      
      if (transactionData.status === 'success') {
        // Success state
        statusElement.classList.add('status-success');
        statusElement.textContent = 'Successful';
        
        // Update timeline images to all ticks
        timelineDots[0].src = 'images/history/tick.png';
        timelineDots[1].src = 'images/history/tick.png';
        timelineDots[2].src = 'images/history/tick.png';
        
        // Update timeline lines to green
        timelineLine1.className = 'timeline-line timeline-line-success';
        timelineLine2.className = 'timeline-line timeline-line-success';
        
        // Show all elements
        timelineSection.classList.remove('hidden');
        timelineLabels.classList.remove('hidden');
        moreActionsSection.classList.remove('hidden');
        actionButtons.classList.remove('hidden');
        disputeButton.classList.add('hidden');
        bubbleSection.classList.remove('hidden');
        
      } else if (transactionData.status === 'failed') {
        // Failed state
        statusElement.classList.add('status-failed');
        statusElement.textContent = 'Failed';
        
        // Hide timeline, more actions, and footer buttons
        timelineSection.classList.add('hidden');
        timelineLabels.classList.add('hidden');
        moreActionsSection.classList.add('hidden');
        actionButtons.classList.add('hidden');
        disputeButton.classList.add('hidden');
        bubbleSection.classList.add('hidden');
        
      } else if (transactionData.status === 'pending') {
        // Pending state
        statusElement.classList.add('status-pending');
        statusElement.textContent = 'Pending';
        
        // Update timeline images
        timelineDots[0].src = 'images/history/tick.png';
        timelineDots[1].src = 'images/history/delay.png';
        timelineDots[2].src = 'images/history/untick.png';
        
        // Update timeline line colors
        timelineLine1.className = 'timeline-line timeline-line-success';
        timelineLine2.className = 'timeline-line timeline-line-pending';
        
        // Show timeline and labels
        timelineSection.classList.remove('hidden');
        timelineLabels.classList.remove('hidden');
        
        // Hide more actions, show only dispute button
        moreActionsSection.classList.add('hidden');
        actionButtons.classList.add('hidden');
        disputeButton.classList.remove('hidden');
        bubbleSection.classList.remove('hidden');
      }
    }

    // Function to change status
    function changeStatus(newStatus) {
      transactionData.status = newStatus;
      updateReceiptDetails();
    }

    // Load transaction details when page loads
    document.addEventListener('DOMContentLoaded', function() {
      updateReceiptDetails();
    });

    // Placeholder functions for actions
    function transferBack() {
      alert('Transfer back functionality would be implemented here.');
    }

    function viewRecords() {
      alert('View records functionality would be implemented here.');
    }
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
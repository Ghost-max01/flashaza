<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php'; // Make sure $pdo is defined in this file
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}
try {
    if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
        $product_id = trim($_GET['product_id']);
        $user_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT * FROM history WHERE uid = :user_id AND product_id = :product_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_STR); 
        $stmt->execute();

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            // No transaction found
            header("Location: history.php");
            exit();
        }
    } else {
        // No product_id in URL
        header("Location: history.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="css/opy-receipt.css">
</head>
<body>
    <div class="container">
        <!-- Loading Overlay -->
        <div class="loading-overlay hidden" id="loadingOverlay" style="display: none;">
            <div class="spinner"></div>
        </div>
        
        <!-- Header -->
        <div class="header">
            <div class="back-btn" onclick="goBack()">‹</div>
            <div class="header-title">Transaction Details</div>
            <img src="images/history/support.png" style="width: 30px; height: 30px;" alt="help">
        </div>
        
        <!-- Main Content -->
        <div class="scroll-container" id="scrollContainer">
            <div class="content-section" id="headSection" style="margin-top: 50px;">
                <!-- Profile Image -->
                <div class="profile-container">
                    <img src="<?php echo isset($transaction['url']) ? htmlspecialchars($transaction['url']) : 'images/dashboard/trade.png'; ?>" 
                         alt="profile" 
                         class="profile-image" 
                         id="profileImage"
                         onerror="this.onerror=null; this.classList.add('fallback'); this.src=''; this.innerHTML='<?php echo isset($transaction['accountname']) ? substr($transaction['accountname'], 0, 1) : 'N'; ?>';">
                </div>
                
                <div class="transaction-from" id="transferFrom">Transfer from Nova Banking</div>
                <div class="transaction-amount" id="amount">₦25,000.00</div>
                
                <div class="status-indicator" id="statusIndicator">
                    <span class="material-icons status-icon status-success" id="statusIcon">check_circle</span>
                    <div class="status-text status-success" id="statusText">Successful</div>
                </div>
            </div>
            
            <!-- Transaction Details Section -->
            <div class="content-section" id="bodySection">
                <div class="section-title" style="margin-top: 7px;">Transaction Details</div>
                
                <div class="detail-row" id="cdRow">
                    <div class="detail-label">Credited to</div>
                    <div class="detail-value">Available Balance</div>
                    <span class="material-icons" style="color: grey;">chevron_right</span>
                </div>
                
                <div class="sender-details">
                    <div class="sender-label" id="optionLabel">Sender Details</div>
                    <div class="sender-info">
                        <div class="sender-name" id="accountName">WEB TECH</div>
                        <div class="sender-bank" id="bankInfo">OPay | 915****789</div>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Transaction No.</div>
                    <div class="detail-value" id="tid">250225010100418357292729</div>
                    <img src="images/history/copy.png" style="width: 15px; height: 15px;" alt="">
                </div>
                
                <div class="detail-row" id="pmedRow">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value" id="paymentMethod">Wallet</div>
                    <span class="material-icons" style="color: grey;">chevron_right</span>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Transaction Date</div>
                    <div class="detail-value" id="date">Feb 25th, 2025 08:19:56</div>
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
                
                <span class="divider-text">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</span>
                
                <div class="more-actions">
                    <div class="action-item" onclick="transferBack()">
                        <img src="images/history/transfer.png" style="width: 20px; height: 20px;" alt="transfer">
                        <span>Transfer Back</span>
                    </div>
                    
                    <div class="action-item" onclick="viewRecords()">
                        <img src="images/history/record.png" style="width: 20px; height: 20px;" alt="record">
                        <span>View Records</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Buttons Container -->
        <div class="footer-container" id="footerContainer">
            <div class="footer-buttons" id="dualButtonFooter">
                <div class="footer-btn report-btn">Report an issue</div>
                <div class="footer-btn share-btn" onclick="window.location.href='share-receipt.php?product_id=<?php echo urlencode($product_id); ?>'">Share Receipt</div>
            </div>
            
            <div class="footer-buttons hidden" id="singleButtonFooter">
                <div class="footer-btn single-btn" onclick="window.location.href='share-receipt.php?product_id=<?php echo urlencode($product_id); ?>'">Share Receipt</div>
            </div>
        </div>
    </div>
    
    <script>
        // Simulate intent data (normally would come from URL parameters)
        const intentData = {
            type: "<?php echo isset($transaction['type']) ? $transaction['type'] : 'received'; ?>",
            status: "<?php echo isset($transaction['status']) ? $transaction['status'] : 'success'; ?>",
            amount: "<?php echo isset($transaction['amount']) ? $transaction['amount'] : '250000.00'; ?>",
            accountname: "<?php echo isset($transaction['accountname']) ? addslashes($transaction['accountname']) : 'John Doe David'; ?>",
            bankname: "<?php echo isset($transaction['bankname']) ? addslashes($transaction['bankname']) : 'OPay'; ?>",
            accountnumber: "<?php echo isset($transaction['accountnumber']) ? $transaction['accountnumber'] : '9151234789'; ?>",
            tid: "<?php echo isset($transaction['tid']) ? $transaction['tid'] : '250225010100418357292729'; ?>",
            date: "<?php echo isset($transaction['date2']) ? $transaction['date2'] : 'Feb 25th, 2025 08:19:56'; ?>",
            url: "<?php echo isset($transaction['url']) ? $transaction['url'] : 'images/dashboard/trade.png'; ?>"
        };

        // DOM elements
        const loadingOverlay = document.getElementById('loadingOverlay');
        const scrollContainer = document.getElementById('scrollContainer');
        const profileImage = document.getElementById('profileImage');
        const headSection = document.getElementById('headSection');
        const bodySection = document.getElementById('bodySection');
        const legSection = document.getElementById('legSection');
        const footerContainer = document.getElementById('footerContainer');
        const dualButtonFooter = document.getElementById('dualButtonFooter');
        const singleButtonFooter = document.getElementById('singleButtonFooter');
        const transferFrom = document.getElementById('transferFrom');
        const amount = document.getElementById('amount');
        const accountName = document.getElementById('accountName');
        const bankInfo = document.getElementById('bankInfo');
        const tid = document.getElementById('tid');
        const dateEl = document.getElementById('date');
        const cdRow = document.getElementById('cdRow');
        const pmedRow = document.getElementById('pmedRow');
        const optionLabel = document.getElementById('optionLabel');
        const statusIcon = document.getElementById('statusIcon');
        const statusText = document.getElementById('statusText');
        const statusIndicator = document.getElementById('statusIndicator');

        // Format amount with commas
        function formatAmount(amount) {
            return parseFloat(amount).toLocaleString('en-NG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Format account number (show first 3 and last 4 digits)
        function formatAccountNumber(accNum) {
            if (accNum.length < 7) return accNum;
            const firstPart = accNum.substring(0, 3);
            const lastPart = accNum.substring(accNum.length - 4);
            return `${firstPart}****${lastPart}`;
        }

        // Set status icon and text based on transaction status
        function setStatusIndicator(status) {
            // Remove all color classes first
            statusIcon.classList.remove('status-success', 'status-failed', 'status-reversed');
            statusText.classList.remove('status-success', 'status-failed', 'status-reversed');

            switch(status) {
                case "success":
                    statusIcon.textContent = "check_circle";
                    statusText.textContent = "Successful";
                    statusIcon.classList.add('status-success');
                    statusText.classList.add('status-success');
                    break;
                case "failed":
                    statusIcon.textContent = "error";
                    statusText.textContent = "Failed";
                    statusIcon.classList.add('status-failed');
                    statusText.classList.add('status-failed');
                    break;
                case "reversed":
                    statusIcon.textContent = "schedule";
                    statusText.textContent = "Reversed";
                    statusIcon.classList.add('status-reversed');
                    statusText.classList.add('status-reversed');
                    break;
                default:
                    statusIcon.textContent = "check_circle";
                    statusText.textContent = "Successful";
                    statusIcon.classList.add('status-success');
                    statusText.classList.add('status-success');
            }
        }

        // Initialize the page
        function initPage() {
            // Disable scrolling initially
            scrollContainer.style.pointerEvents = 'none';
            
            // Hide elements initially (simulating Android visibility settings)
            headSection.classList.add('hidden');
            bodySection.classList.add('hidden');
            legSection.classList.add('hidden');
            footerContainer.classList.add('hidden');
            cdRow.classList.add('hidden');
            pmedRow.classList.add('hidden');

            // Show loading
            loadingOverlay.classList.remove('hidden');

            // Simulate loading process
            setTimeout(() => {
                // Enable scrolling
                scrollContainer.style.pointerEvents = 'auto';
                
                // Hide loading
                loadingOverlay.classList.add('hidden');
                
                // Show elements
                headSection.classList.remove('hidden');
                bodySection.classList.remove('hidden');
                legSection.classList.remove('hidden');
                footerContainer.classList.remove('hidden');

                // Set data from intent
                amount.textContent = `₦${formatAmount(intentData.amount)}`;
                accountName.textContent = intentData.accountname;
                bankInfo.textContent = `${intentData.bankname} | ${formatAccountNumber(intentData.accountnumber)}`;
                tid.textContent = intentData.tid;
                dateEl.textContent = intentData.date;

                // Set status indicator
                setStatusIndicator(intentData.status);

                // Configure UI based on transaction type
                if (intentData.type === "sent") {
                    pmedRow.classList.remove('hidden');
                    cdRow.classList.add('hidden');
                    transferFrom.textContent = `Transfer to ${intentData.accountname}`;
                    optionLabel.textContent = "Recipient Details";
                    
                    if (intentData.status === "success") {
                        dualButtonFooter.classList.remove('hidden');
                        singleButtonFooter.classList.add('hidden');
                    } else {
                        dualButtonFooter.classList.add('hidden');
                        singleButtonFooter.classList.add('hidden');
                    }
                } else {
                    // Received transaction
                    pmedRow.classList.add('hidden');
                    cdRow.classList.remove('hidden');
                    transferFrom.textContent = `Transfer from ${intentData.accountname}`;
                    optionLabel.textContent = "Sender Details";
                    
                    if (intentData.status === "success") {
                        dualButtonFooter.classList.add('hidden');
                        singleButtonFooter.classList.remove('hidden');
                    } else {
                        dualButtonFooter.classList.add('hidden');
                        singleButtonFooter.classList.add('hidden');
                    }
                }
            }, 1000); // Simulate 1 second loading time
        }

        // Initialize the page when loaded
        window.onload = initPage;

        // Button click handlers
        function goBack() {
            alert('Back button clicked!');
            // In a real app: window.history.back() or navigate to previous page
        }

        function showHelp() {
            alert('Help button clicked!');
        }

        function reportIssue() {
            alert('Report issue button clicked!');
        }

        function shareReceipt() {
            alert('Share receipt button clicked!');
        }

        function transferBack() {
            alert('Transfer back button clicked!');
        }

        function viewRecords() {
            alert('View records button clicked!');
        }
    </script>
</body>
</html>
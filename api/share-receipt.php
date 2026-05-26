<?PHP
session_start();  

// 1) require login  
if (!isset($_SESSION['user_id'])) {  
    header("Location: login.php");  
    exit();  
}  

// 2) load DB  
require_once 'config.php'; // must set $pdo (PDO instance)  
if (!isset($pdo) || !($pdo instanceof PDO)) {  
    die("DB error: \$pdo is not defined or not a PDO instance. Check config.php");  
}  
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

$uid = $_SESSION['user_id'];  
// 3) get product_id from URL  
$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';  

// if no product_id, redirect back to history or list page  
if (empty($product_id)) {  
    header("Location: history.php");  
    exit();  
}  

// helper functions  
function mask_number($num) {  
    $n = preg_replace('/\D+/', '', $num);  
    if (strlen($n) <= 6) return $n;  
    $first = substr($n, 0, 3);  
    $last  = substr($n, -3);  
    return $first . '****' . $last;  
}  
function format_with_spaces($num) {  
    $n = preg_replace('/\D+/', '', $num);  
    if (strlen($n) === 10) {  
        return substr($n,0,3) . ' ' . substr($n,3,3) . ' ' . substr($n,6,4);  
    }  
    return $n;  
}  

try {  
    // fetch history row by product_id  
    $stmt = $pdo->prepare("SELECT * FROM history WHERE product_id = :pid LIMIT 1");  
    $stmt->execute([':pid' => $product_id]);  
    $history = $stmt->fetch(PDO::FETCH_ASSOC);  

    if (!$history) {  
        header("Location: history.php");  
        exit();  
    }  

    // fetch current user  
    $stmt = $pdo->prepare("SELECT * FROM users WHERE uid = :uid LIMIT 1");  
    $stmt->execute([':uid' => $uid]);  
    $user = $stmt->fetch(PDO::FETCH_ASSOC);  

    if (!$user) {  
        header("Location: login.php");  
        exit();  
    }  

} catch (PDOException $e) {  
    die("DB error: " . $e->getMessage());  
}  

$amount_value = isset($history['amount']) ? $history['amount'] : 0;  
$amount_formatted = number_format((float)$amount_value, 2, '.', ',');  

$date1_raw = $history['date1'] ?? '';  
$date_display = '';  
if (!empty($date1_raw)) {  
    $ts = strtotime($date1_raw);  
    if ($ts !== false) {  
        $date_display = date('M, jS Y H:i:s', $ts);  
    } else {  
        $date_display = htmlspecialchars($date1_raw);  
    }  
}  

$transaction_no = $history['tid'] ?? '';  
$session_id_val = $history['sid'] ?? '';  
$status_text = $history['status'] ?? 'Successful';  

$type = strtolower(trim($history['type'] ?? ''));  
$history_bank = $history['bankname'] ?? '';  

if ($type === 'sent') {  
    $recipient_name = $history['accountname'] ?? '';  
    $recipient_bank = $history['bankname'] ?? '';  
    $recipient_account_raw = $history['accountnumber'] ?? '';  
    $recipient_account_display = htmlspecialchars($recipient_bank) . ' | ' . htmlspecialchars($recipient_account_raw);  

    $sender_name = $user['name'] ?? $user['fullname'] ?? '';  
    $sender_bank = 'OPay';  
    $sender_account_raw = $user['number'] ?? $user['accountnumber'] ?? '';  
    $sender_account_display = htmlspecialchars($sender_bank) . ' | ' . htmlspecialchars(mask_number($sender_account_raw));  

    if (strcasecmp($history_bank, 'OPay') === 0) {  
        $show_transaction_type = false;  
        $show_session_id = false;  
    } else {  
        $show_transaction_type = false;  
        $show_session_id = true;  
    }  
} else {  
    $recipient_name = $user['name'] ?? $user['fullname'] ?? '';  
    $recipient_bank = 'OPay';  
    $recipient_account_raw = $user['number'] ?? $user['accountnumber'] ?? '';  
    $recipient_account_display = htmlspecialchars($recipient_bank . ' | ' . format_with_spaces($recipient_account_raw));  

    $sender_name = $history['accountname'] ?? '';  
    $sender_bank = $history['bankname'] ?? '';  
    $sender_account_raw = $history['accountnumber'] ?? '';  
    $sender_account_display = htmlspecialchars($sender_bank) . ' | ' . htmlspecialchars(mask_number($sender_account_raw));  

    $show_transaction_type = true;  
    $show_session_id = true;  
}  

$transaction_type_value = $history['transaction_type'] ?? '';  

$recipient_name_safe = htmlspecialchars($recipient_name);  
$sender_name_safe = htmlspecialchars($sender_name);  

$transaction_no_safe = htmlspecialchars($transaction_no);  
$session_id_safe = htmlspecialchars($session_id_val);  
?>  
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<title>Transaction Receipt</title>
<link rel="stylesheet" href="css/share-receipt.css?v=1.0">
</head>
<body>
<!-- Header -->
<div class="header">
    <span class="material-icons return-icon" id="returnIcon">chevron_left</span>
    <div class="header-text">Share Receipt</div>
</div>

<!-- Content -->
<div class="content">
    <div class="receipt-card" id="receiptCard">
        <div class="inner-card">
            <div class="receipt-title">
                <img src="images/dashboard/qr_opay.png" alt="qr">
                <div>Transaction Receipt</div>
            </div>
            <div class="transaction-box">
                <div class="amount">₦<?= htmlspecialchars($amount_formatted) ?></div>
                <div class="status"><?= htmlspecialchars($status_text) ?></div>
                <div class="date"><?= htmlspecialchars($date_display) ?></div>
            </div>
            <div class="divider"></div>
            
            <!-- Recipient Details -->
            <div class="row">
                <div class="label">Recipient Details</div>
                <div class="value">
                    <?= $recipient_name_safe ?><br>
                    <span style="color:#757575;"><?= $recipient_account_display ?></span>
                </div>
            </div>
            
            <!-- Sender Details -->
            <div class="row">
                <div class="label">Sender Details</div>
                <div class="value">
                    <?= $sender_name_safe ?><br>
                    <span style="color:#757575;"><?= $sender_account_display ?></span>
                </div>
            </div>
            
            <!-- Transaction Type (conditional) -->
            <div class="row" style="<?= $show_transaction_type ? '' : 'display:none;' ?>">
                <div class="label">Transaction Type</div>
                <div class="value"><?= htmlspecialchars($transaction_type_value ?: 'Bank Transfer') ?></div>
            </div>
            
            <!-- Transaction Number -->
            <div class="row">
                <div class="label">Transaction No.</div>
                <div class="value"><?= $transaction_no_safe ?></div>
            </div>
            
            <!-- Session ID (conditional) -->
            <div class="row" style="<?= $show_session_id ? '' : 'display:none;' ?>">
                <div class="label">Session ID</div>
                <div class="value"><?= $session_id_safe ?></div>
            </div>
            
            <div class="footer-note">
                Enjoy a better life with OPay. Get free transfers, withdrawals,<br>
                bill payments, instant loans, and good annual interest on your savings.
                OPay is licensed by the Central Bank of Nigeria and insured by the NDIC.
            </div>
        </div>
    </div>
</div>

<!-- Share modal -->
<div id="shareModal" aria-hidden="true">
    <div class="box">
        <p><strong>Share receipt</strong></p>
        <div>
            <button id="shareWhatsappBtn"><i class="fab fa-whatsapp"></i> WhatsApp</button>
            <button id="downloadBtn">Download</button>
            <button id="copyBtn">Copy</button>
        </div>
        <div style="margin-top:8px">
            <button id="closeShareModal">Close</button>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div id="shareImageBtn"><img src="images/history/image.png" alt="share">Share as image</div>
    <div class="separator">|</div>
    <div id="sharePdfBtn"><img src="images/history/pdf.png" alt="pdf">Share as PDF</div>
</div>

<!-- libs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
// small helpers
const receiptEl = document.getElementById('receiptCard'); // Changed to capture the entire card
const shareModal = document.getElementById('shareModal');
const shareImageBtn = document.getElementById('shareImageBtn');
const sharePdfBtn = document.getElementById('sharePdfBtn');
const shareWhatsappBtn = document.getElementById('shareWhatsappBtn');
const downloadBtn = document.getElementById('downloadBtn');
const copyBtn = document.getElementById('copyBtn');
const closeShareModal = document.getElementById('closeShareModal');
let lastBlobUrl = null; // cache

// Check if device is in dark mode
function isDarkMode() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

async function captureReceiptBlob() {
    // Store original styles
    const originalReceiptFilter = receiptEl.style.filter;
    const originalTransactionFilter = document.querySelector('.transaction-box').style.filter;
    const originalImages = [];
    
    document.querySelectorAll('.receipt-card img').forEach((img, index) => {
        originalImages[index] = img.style.filter;
    });
    
    // If in dark mode, we need to create a light version for capture
    if (isDarkMode()) {
        // Create a light version by inverting the dark mode filters
        receiptEl.style.filter = 'invert(1) hue-rotate(180deg) contrast(0.9) brightness(1.1)';
        document.querySelector('.transaction-box').style.filter = 'invert(1) hue-rotate(180deg)';
        document.querySelectorAll('.receipt-card img').forEach(img => {
            img.style.filter = 'invert(1) hue-rotate(180deg)';
        });
    }
    
    // Render with all background elements
    const canvas = await html2canvas(receiptEl, {
        scale: 2,
        useCORS: true,
        backgroundColor: null, // transparent background
        logging: false,
        removeContainer: true,
        onclone: function(clonedDoc) {
            // Ensure pseudo-elements are captured
            const clonedReceipt = clonedDoc.getElementById('receiptCard');
            if (clonedReceipt) {
                // Force light mode appearance for capture
                clonedReceipt.style.filter = 'none';
                const clonedTransactionBox = clonedReceipt.querySelector('.transaction-box');
                if (clonedTransactionBox) {
                    clonedTransactionBox.style.filter = 'none';
                }
                
                // Reset image filters
                clonedReceipt.querySelectorAll('img').forEach(img => {
                    img.style.filter = 'none';
                });
                
                // Set background images explicitly
                clonedReceipt.style.backgroundImage = "url('images/history/background.png')";
                const innerCard = clonedReceipt.querySelector('.inner-card');
                if (innerCard) {
                    innerCard.style.backgroundImage = "url('images/history/watermark.png')";
                }
                
                const transactionBox = clonedReceipt.querySelector('.transaction-box');
                if (transactionBox) {
                    transactionBox.style.backgroundImage = "url('better_bg.png')";
                }
            }
        }
    });
    
    // Restore original styles
    receiptEl.style.filter = originalReceiptFilter;
    document.querySelector('.transaction-box').style.filter = originalTransactionFilter;
    document.querySelectorAll('.receipt-card img').forEach((img, index) => {
        img.style.filter = originalImages[index];
    });
    
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/png', 1));
}

// open share modal and populate download/copy links
function openShareModal(blob, filename) {
    if (lastBlobUrl) URL.revokeObjectURL(lastBlobUrl);
    lastBlobUrl = URL.createObjectURL(blob);
    
    downloadBtn.onclick = () => {
        const a = document.createElement('a');
        a.href = lastBlobUrl;
        a.download = filename || 'receipt.png';
        document.body.appendChild(a);
        a.click();
        a.remove();
    };
    
    shareWhatsappBtn.onclick = () => {
        const text = encodeURIComponent('I just sent a receipt. You can download it from this device and share it.');
        window.open('https://wa.me/?text=' + text, '_blank');
    };
    
    copyBtn.onclick = async () => {
        try {
            if (!navigator.clipboard) throw "Clipboard API not supported";
            const file = new File([blob], 'receipt.png', { type: 'image/png' });
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': file })]);
            alert('Image copied to clipboard. Paste in chat/apps that accept images.');
        } catch (err) {
            console.error(err);
            alert('Copy to clipboard not supported. Please download the image instead.');
        }
    };
    
    closeShareModal.onclick = () => {
        shareModal.style.display = 'none';
    };
    
    shareModal.style.display = 'flex';
}

// Try to share file via Web Share API (files) when available
async function tryNativeShareFile(file, title = 'Receipt') {
    if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try {
            await navigator.share({
                files: [file],
                title: title,
                text: 'Transaction receipt'
            });
            return true;
        } catch (err) {
            console.warn('native share failed', err);
            return false;
        }
    }
    return false;
}

// Share as image
shareImageBtn.addEventListener('click', async () => {
    try {
        const blob = await captureReceiptBlob();
        const file = new File([blob], 'receipt.png', { type: 'image/png' });
        const ok = await tryNativeShareFile(file, 'Transaction receipt');
        
        if (!ok) {
            openShareModal(blob, 'receipt.png');
        }
    } catch (err) {
        console.error(err);
        alert('Failed to capture image.');
    }
});

// Share as PDF
sharePdfBtn.addEventListener('click', async () => {
    try {
        const blob = await captureReceiptBlob();
        const imgUrl = URL.createObjectURL(blob);
        const img = new Image();
        img.src = imgUrl;
        
        img.onload = async () => {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'pt', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            
            const ratio = Math.min(pageWidth / img.width, pageHeight / img.height);
            const imgWidth = img.width * ratio;
            const imgHeight = img.height * ratio;
            
            pdf.addImage(img, 'PNG', (pageWidth - imgWidth) / 2, 20, imgWidth, imgHeight);
            const pdfBlob = pdf.output('blob');
            const file = new File([pdfBlob], 'receipt.pdf', { type: 'application/pdf' });
            
            const shared = await tryNativeShareFile(file, 'Transaction receipt (PDF)');
            if (!shared) {
                openShareModal(pdfBlob, 'receipt.pdf');
            }
            
            URL.revokeObjectURL(imgUrl);
        };
    } catch (err) {
        console.error(err);
        alert('Failed to generate PDF.');
    }
});

// close modal when clicking outside
shareModal.addEventListener('click', (e) => {
    if (e.target === shareModal) shareModal.style.display = 'none';
});

// Return/back icon
document.getElementById('returnIcon').addEventListener('click', () => {
    window.history.back();
});

// Listen for dark mode changes
if (window.matchMedia) {
    const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    darkModeMediaQuery.addListener((e) => {
        // Reload the page when dark mode changes
        window.location.reload();
    });
}
</script>
</body>
</html>
<?php
// FORCE error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (function_exists('ob_clean')) ob_clean();
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

/*
bulk_email_sender.php - Improved version with batch processing
*/

// ----------------- User configuration -----------------
if (!file_exists(__DIR__ . '/config.php')) {
    die("config.php not found – please create it with PDO connection.");
}
require_once 'config.php'; // PDO database connection

if (!file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
    die("PHPMailer not found – please put PHPMailer inside this directory.");
}
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ----------------- SMTP settings -----------------
$smtp = [
    'host'       => 'mail.webtech.net.ng',
    'port'       => 587,
    'username'   => 'clone@webtech.net.ng',
    'password'   => 'Said0051$',
    'secure'     => 'tls', 
    'from_email' => 'clone@webtech.net.ng',
    'from_name'  => 'WEB TECH',
    'reply_to'   => 'support@webtech.net.ng',
];

$batchSize = 100; // Increased batch size for better performance
$sleepBetween = 0.1; // Reduced sleep time

// ----------------- Helper functions -----------------
function safe_trim($s) { return trim((string)$s); }

function buildAltBody($html) {
    $text = html_entity_decode(strip_tags($html));
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function addListUnsubscribeHeader(PHPMailer $m, $unsubscribeUrl) {
    if ($unsubscribeUrl) {
        $m->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
    }
}

// ----------------- Handle AJAX batch requests -----------------
if (isset($_GET['ajax']) && $_GET['ajax'] == 'send_batch') {
    header('Content-Type: application/json');
    
    $batch = isset($_GET['batch']) ? (int)$_GET['batch'] : 0;
    $subject = isset($_GET['subject']) ? urldecode($_GET['subject']) : '';
    $html = isset($_GET['html']) ? urldecode($_GET['html']) : '';
    
    if (empty($subject) || empty($html)) {
        echo json_encode(['error' => 'Missing subject or HTML content']);
        exit;
    }
    
    try {
        $offset = $batch * $batchSize;
        $query = "SELECT id, email FROM users WHERE email IS NOT NULL AND email != '' LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sent = 0;
        $failed = 0;
        $failLog = [];
        
        if ($rows) {
            foreach ($rows as $row) {
                $toEmail = $row['email'];
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $smtp['host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp['username'];
                    $mail->Password   = $smtp['password'];
                    $mail->SMTPSecure = $smtp['secure'];
                    $mail->Port       = $smtp['port'];
                    $mail->SMTPKeepAlive = true; // Keep connection alive for multiple emails

                    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                    $mail->addReplyTo($smtp['reply_to']);
                    $mail->addAddress($toEmail);

                    $mail->Subject = $subject;
                    $mail->isHTML(true);
                    $mail->Body = $html;
                    $mail->AltBody = buildAltBody($html);

                    $unsubscribeUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . 
                        $_SERVER['HTTP_HOST'] . '/unsubscribe.php?uid=' . urlencode($row['id']);
                    addListUnsubscribeHeader($mail, $unsubscribeUrl);

                    $mail->addCustomHeader('X-Mailer', 'WEBTECH Mailer');
                    $mail->addCustomHeader('X-Priority', '3');
                    $mail->addCustomHeader('Organization', 'WEB TECH');

                    $mail->send();
                    $sent++;
                } catch (Exception $e) {
                    $failed++;
                    $failLog[] = ['email' => $toEmail, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
                }
                
                // Clear addresses for next email
                $mail->clearAddresses();
                $mail->clearCustomHeaders();
                
                usleep((int)($sleepBetween * 1000000));
            }
            
            // Close SMTP connection if it's kept alive
            if (isset($mail)) {
                $mail->smtpClose();
            }
        }
        
        echo json_encode([
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'failLog' => $failLog,
            'batch' => $batch,
            'completed' => count($rows) < $batchSize
        ]);
    } catch (Exception $ex) {
        echo json_encode(['error' => 'Batch processing error: ' . $ex->getMessage()]);
    }
    exit;
}

// ----------------- Handle form submit (test email only) -----------------
$errors = [];
$success = null;
$estimatedCount = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = safe_trim($_POST['subject'] ?? '');
    $html = $_POST['html'] ?? '';
    $test_email = filter_var($_POST['test_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
    $send_test = isset($_POST['send_test']);

    if ($subject === '') $errors[] = 'Subject is required.';
    if ($html === '') $errors[] = 'HTML body is required.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND email != ''");
            $stmt->execute();
            $estimatedCount = (int)$stmt->fetchColumn();
        } catch (Exception $ex) {
            $errors[] = 'Database error: ' . $ex->getMessage();
        }
    }

    if (empty($errors) && $send_test && $test_email) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['port'];

            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
            $mail->addAddress($test_email);
            $mail->addReplyTo($smtp['reply_to']);
            $mail->Subject = $subject . ' [TEST]';
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = buildAltBody($html);

            addListUnsubscribeHeader($mail, 'mailto:' . $smtp['reply_to']);

            $mail->addCustomHeader('X-Mailer', 'WEBTECH Mailer');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('Organization', 'WEB TECH');

            $mail->send();
            $success = "Test email sent to {$test_email}.";
        } catch (Exception $e) {
            $errors[] = 'Test email failed: ' . $mail->ErrorInfo;
        }
    } elseif (empty($errors) && !$send_test) {
        // For actual sending, we'll handle it via AJAX
        $startSending = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Bulk Email Sender</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; padding: 24px; }
        .wrap { max-width: 900px; margin: 0 auto; }
        label { display:block; margin-top:12px; font-weight:600; }
        input[type=text], textarea { width:100%; padding:10px; border-radius:8px; border:1px solid #ddd; }
        textarea { min-height: 240px; font-family: monospace; }
        .small { font-size: .9rem; color:#555; }
        .btn { display:inline-block; padding:10px 16px; border-radius:8px; background:#0b74de; color:white; text-decoration:none; border:none; cursor:pointer; }
        .btn.secondary { background:#666; }
        .btn:disabled { background:#ccc; cursor:not-allowed; }
        .notice { padding:10px; border-radius:8px; margin-top:12px; }
        .error { background:#ffe6e6; border:1px solid #ffb3b3; }
        .success { background:#e6ffe6; border:1px solid #b3ffb3; }
        .progress-container { margin-top: 20px; display: none; }
        .progress-bar { height: 20px; background-color: #f0f0f0; border-radius: 10px; overflow: hidden; }
        .progress { height: 100%; background-color: #0b74de; width: 0%; transition: width 0.3s; }
        .progress-status { margin-top: 10px; }
        #startSending { margin-top: 15px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Bulk Email Sender</h1>

    <?php if (!empty($errors)): ?>
        <div class="notice error">
            <strong>Errors:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?=htmlspecialchars($e)?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="notice success"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <form id="emailForm" method="post">
        <label>Subject</label>
        <input type="text" name="subject" value="<?=isset($subject) ? htmlspecialchars($subject) : ''?>" required>

        <label>HTML body</label>
        <textarea name="html" required><?=isset($html) ? htmlspecialchars($html) : '<p>Hello,</p><p>This is a test message.</p>'?></textarea>

        <label class="small">Send a test to</label>
        <input type="text" name="test_email" placeholder="you@yourdomain.com">
        <label class="small"><input type="checkbox" name="send_test"> Send test (won't send to users)</label>

        <div style="margin-top:12px">
            <button class="btn" type="submit">Validate and Preview</button>
            <button class="btn secondary" type="button" onclick="document.querySelector('textarea[name=html]').value += '\n\n<p>--<br>To unsubscribe, click here: {{unsubscribe_link}}</p>'">Add unsubscribe placeholder</button>
        </div>

        <?php if ($estimatedCount !== null): ?>
            <p class="small">Estimated recipients: <?=htmlspecialchars($estimatedCount)?></p>
        <?php endif; ?>
    </form>
    
    <?php if (isset($startSending) && empty($errors)): ?>
        <div id="startSending">
            <button class="btn" id="startSendingBtn">Start Sending to All Users</button>
            <div class="progress-container" id="progressContainer">
                <div class="progress-bar">
                    <div class="progress" id="progressBar"></div>
                </div>
                <div class="progress-status" id="progressStatus">Preparing to send...</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('emailForm').addEventListener('submit', function(e) {
    // Only prevent default if we're doing actual sending (not test)
    if (!document.querySelector('input[name="send_test"]').checked) {
        e.preventDefault();
        this.querySelector('button[type="submit"]').textContent = 'Validating...';
        
        // Simple validation
        const subject = document.querySelector('input[name="subject"]').value;
        const html = document.querySelector('textarea[name="html"]').value;
        
        if (!subject || !html) {
            alert('Subject and HTML content are required');
            this.querySelector('button[type="submit"]').textContent = 'Validate and Preview';
            return;
        }
        
        // Submit the form for validation and recipient count
        this.submit();
    }
});

<?php if (isset($startSending) && empty($errors)): ?>
document.getElementById('startSendingBtn').addEventListener('click', function() {
    this.disabled = true;
    document.getElementById('progressContainer').style.display = 'block';
    
    const subject = encodeURIComponent(document.querySelector('input[name="subject"]').value);
    const html = encodeURIComponent(document.querySelector('textarea[name="html"]').value);
    const totalRecipients = <?=$estimatedCount?>;
    const batchSize = <?=$batchSize?>;
    const totalBatches = Math.ceil(totalRecipients / batchSize);
    let currentBatch = 0;
    let totalSent = 0;
    let totalFailed = 0;
    let failLog = [];
    
    function sendNextBatch() {
        if (currentBatch >= totalBatches) {
            // All batches completed
            const statusElement = document.getElementById('progressStatus');
            statusElement.innerHTML = `Completed! Sent: ${totalSent}, Failed: ${totalFailed}`;
            
            if (failLog.length > 0) {
                // Create a download link for the fail log
                const blob = new Blob([JSON.stringify(failLog, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'email_fail_log_' + Date.now() + '.json';
                a.textContent = 'Download Error Log';
                statusElement.appendChild(document.createElement('br'));
                statusElement.appendChild(a);
            }
            
            return;
        }
        
        // Update progress
        const progress = Math.round((currentBatch / totalBatches) * 100);
        document.getElementById('progressBar').style.width = progress + '%';
        document.getElementById('progressStatus').textContent = 
            `Processing batch ${currentBatch + 1} of ${totalBatches} (${progress}%)`;
        
        // Send the batch via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `?ajax=send_batch&batch=${currentBatch}&subject=${subject}&html=${html}`, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            document.getElementById('progressStatus').textContent = 'Error: ' + response.error;
                            return;
                        }
                        
                        totalSent += response.sent;
                        totalFailed += response.failed;
                        
                        if (response.failLog && response.failLog.length > 0) {
                            failLog = failLog.concat(response.failLog);
                        }
                        
                        currentBatch++;
                        setTimeout(sendNextBatch, 100); // Small delay before next batch
                    } catch (e) {
                        document.getElementById('progressStatus').textContent = 'Error parsing response: ' + e.message;
                    }
                } else {
                    document.getElementById('progressStatus').textContent = 'Request failed with status: ' + xhr.status;
                }
            }
        };
        xhr.send();
    }
    
    // Start the process
    sendNextBatch();
});
<?php endif; ?>
</script>
</body>
</html>
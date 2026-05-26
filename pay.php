<?php
session_start();

// Enable error reporting (optional: turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Load config
require_once 'config.php';

// Fetch URL params
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$plan   = isset($_GET['plan']) ? $_GET['plan'] : "none";

// Function to generate 20-character random string for request_id
function generateRequestID($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

try {
    // Fetch Bank account details
    $stmt = $pdo->prepare("SELECT bank_name, account_name, account_number FROM bank_details LIMIT 1");
    $stmt->execute();
    $bank = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bank) {
        $bankName      = $bank['bank_name'];
        $accountName   = $bank['account_name'];
        $accountNumber = $bank['account_number'];
    } else {
        $bankName = $accountName = $accountNumber = "N/A";
    }

    // User details
    $stmt = $pdo->prepare("SELECT name, email, number FROM users WHERE uid = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found");
    }

    $userName  = $user['name'];
    $userEmail = $user['email'];
    $userPhone = $user['number'] ?? "Not Provided";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// File upload + DB insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    $useSupabase = getenv('SUPABASE_URL') && getenv('SUPABASE_SERVICE_ROLE_KEY') && getenv('SUPABASE_BUCKET_NAME');
    $targetDir = $useSupabase ? (sys_get_temp_dir() . '/') : "request/";
    if (!$useSupabase && !file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            echo json_encode(["status" => false, "message" => "Failed to create upload directory."]);
            exit();
        }
    }
    
    $fileName = time() . '_' . basename($_FILES["receipt"]["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    $allowTypes = ['jpg','png','jpeg','gif','pdf'];
    
    if (in_array(strtolower($fileType), $allowTypes)) {
            if (move_uploaded_file($_FILES["receipt"]["tmp_name"], $targetFilePath)) {
            try {
                // Generate unique request_id
                $request_id = generateRequestID(20);

                // Determine image URL. If Supabase storage is configured, upload there and use public URL.
                $imageUrl = '';
                if ($useSupabase) {
                    require_once __DIR__ . '/supabase_storage.php';
                    try {
                        $remotePath = 'receipts/' . $fileName;
                        $imageUrl = supabase_upload_file($targetFilePath, $remotePath);
                        // remove local copy after successful upload
                        if (file_exists($targetFilePath)) {
                            @unlink($targetFilePath);
                        }
                    } catch (Exception $e) {
                        // If storage upload fails, return error to client
                        echo json_encode(["status" => false, "message" => "Storage upload failed: " . $e->getMessage()]);
                        // cleanup local file
                        if (file_exists($targetFilePath)) {@unlink($targetFilePath);} 
                        exit();
                    }
                } else {
                    // Build image URL from BASE_URL env var when available (fallback to original behavior)
                    $base = getenv('BASE_URL') ?: '';
                    if ($base) {
                        $imageUrl = rtrim($base, '/') . '/request/' . $fileName;
                    } else {
                        $imageUrl = "https://webtech.net.ng/OPay/request/" . $fileName;
                    }
                }

                $sql = "INSERT INTO payment_requests (request_id, uid, name, number, email, image, date, plan, status) 
                        VALUES (:request_id, :uid, :name, :number, :email, :image, NOW(), :plan, 'pending')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':request_id' => $request_id,
                    ':uid' => $_SESSION['user_id'],
                    ':name' => $userName,
                    ':number' => $userPhone,
                    ':email' => $userEmail,
                    ':image' => $imageUrl,
                    ':plan' => $plan
                ]);

                echo json_encode(["status" => true, "message" => "Image uploaded successfully"]);
                exit();
            } catch (PDOException $e) {
                echo json_encode(["status" => false, "message" => $e->getMessage()]);
                exit();
            }
        } else {
            echo json_encode(["status" => false, "message" => "Error uploading your file."]);
            exit();
        }
    } else {
        echo json_encode(["status" => false, "message" => "Only JPG, JPEG, PNG, GIF, & PDF files allowed."]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Bank Transfer</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="css/pay.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="back-icon" onclick="window.history.back()"><i class="material-icons">arrow_back</i></div>
            <div class="title">Bank Transfer</div>
        </div>
        <div class="content">
            <div class="info-text">Make A Transfer Of The Subscription Amount To The Account Number Below</div>
            
            <!-- iOS Warning Message -->
            <div class="ios-warning" id="iosWarning">
                <strong>iOS Users:</strong> If you experience issues uploading images, please try these steps:
                <ol>
                    <li>Ensure you're using the latest version of iOS</li>
                    <li>Try using the Chrome browser instead of Safari</li>
                    <li>Check that you have sufficient storage space</li>
                </ol>
            </div>
            
            <!-- Account Details Card -->
            <div class="card" id="accountCard">
                <div class="status-row">
                    <div class="status-dot"></div>
                    <div class="status-text">Active</div>
                </div>
                <div class="detail-row">
                    <div class="icon"><i class="material-icons">account_balance</i></div>
                    <div class="detail-content">
                        <div class="detail-label">Bank Name</div>
                        <div class="detail-value" id="acctBank"><?php echo htmlspecialchars($bankName); ?></div>
                    </div>
                </div>
                <div class="divider"></div>
                <div class="detail-row">
                    <div class="icon"><i class="material-icons">pin</i></div>
                    <div class="detail-content">
                        <div class="detail-label">Account Number</div>
                        <div class="detail-value account-number" id="acctNumb"><?php echo htmlspecialchars($accountNumber); ?></div>
                    </div>
                </div>
                <div class="divider"></div>
                <div class="detail-row">
                    <div class="icon"><i class="material-icons">person</i></div>
                    <div class="detail-content">
                        <div class="detail-label">Account Name</div>
                        <div class="detail-value" id="trfName"><?php echo htmlspecialchars($accountName); ?></div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="icon"><i class="material-icons">payments</i></div>
                    <div class="detail-content">
                        <div class="detail-label">Amount</div>
                        <div class="detail-value" id="amount">₦<?php echo number_format($amount, 2); ?></div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="icon"><i class="material-icons">subscriptions</i></div>
                    <div class="detail-content">
                        <div class="detail-label">Plan</div>
                        <div class="detail-value" id="plan"><?php echo htmlspecialchars($plan); ?></div>
                    </div>
                </div>
                <button class="action-button" id="verifyButton">I Have Made Payment</button>
            </div>
            
            <!-- Copy button moved outside the card -->
            <div class="copy-button" id="copyButton">
                <i class="material-icons">content_copy</i>
                <div class="copy-text">Copy Account Number</div>
            </div>
            
            <!-- Upload Section -->
            <div class="upload-section" id="uploadSection">
                <div class="section-title">Upload Payment Receipt</div>
                <div class="section-description">
                    Kindly upload your payment receipt proof and submit. Please wait 5-10 minutes for your account to get activated. 
                    Note that uploading a fake receipt may lead to account suspension.
                </div>
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="upload-area">
                        <input type="file" id="fileInput" name="receipt" class="file-input" accept=".jpg,.jpeg,.png,.gif,.pdf">
                        <button type="button" class="upload-button" id="uploadBtn"><i class="material-icons upload-icon">cloud_upload</i>Upload Receipt</button>
                        <div class="file-name" id="fileName">No file chosen</div>
                        <div class="progress-bar" id="progressBar">
                            <div class="progress" id="progress"></div>
                        </div>
                        <div class="upload-status" id="uploadStatus">Uploading... 0%</div>
                    </div>
                </form>
                <div class="submit-area disabled" id="submitArea">
                    <div class="submit-text" id="submitText">Submit Verification</div>
                </div>
            </div>
            
            <!-- Confirmation Section -->
            <div class="confirmation-section" id="confirmationSection">
                <div class="confirmation-icon"><i class="material-icons">check_circle</i></div>
                <div class="confirmation-title">Payment Verified Successfully!</div>
                <div class="confirmation-text">
                    Your payment has been verified and your account details are now hidden for security purposes.<br>
                    You will receive a confirmation email shortly.
                </div>
                <button class="action-button" id="doneButton">Done</button>
            </div>
        </div>
    </div>

<script src="js/pay.js" defer></script>
</body>
</html>
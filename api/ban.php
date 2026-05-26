<?php
session_start();

// Check if user is logged in, otherwise redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database configuration
include 'config.php';

// Fetch support URL from database
$support_url = "#";
try {
    $stmt = $pdo->prepare("SELECT price FROM price WHERE type = 'support'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $support_url = $result['price'];
    }
} catch (PDOException $e) {
    $support_url = "#";
}

// Handle exit button - log out and redirect to login
if (isset($_GET['action']) && $_GET['action'] == 'exit') {
    unset($_SESSION['user_id']);
    session_destroy();
    header('Location: login.php');
    exit();
}

// Handle back button - log out and redirect to login
if (isset($_GET['action']) && $_GET['action'] == 'back') {
    unset($_SESSION['user_id']);
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Banned</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/ban.css">
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="back-icon" onclick="handleBack()">
                <span class="material-icons return-icon" id="returnIcon">chevron_left</span>
            </div>
            <div class="header-text">ACCOUNT BANNED</div>
        </div>
        
        <!-- Content Area -->
        <div class="content">
            <!-- Banned Icon -->
            <div class="banned-icon">
                <img src="images/dashboard/ban.png" alt="Banned">
            </div>
            
            <!-- Banned Title -->
            <div class="banned-title">
                ACCOUNT BANNED
            </div>
            
            <!-- Banned Message -->
            <div class="banned-message">
                Dear User, Your Account Caught Cheating Or Caught With Some Activity Not Associated With Our Guidelines If You Think This A Mistake Contact The Support
            </div>
            
            <!-- Contact Support Button -->
            <div class="contact-support-btn" id="contact-support" onclick="contactSupport()">
                <div class="contact-support-text">Contact Support</div>
            </div>
            
            <!-- Exit Button -->
            <div class="exit-btn" id="exit-btn" onclick="handleExit()">
                <div class="exit-text">EXIT</div>
            </div>
        </div>
    </div>

    <script>
        // Function to handle contact support button
        function contactSupport() {
            window.open('<?php echo $support_url; ?>', '_blank');
        }
        
        // Function to handle exit button
        function handleExit() {
            window.location.href = 'ban.php?action=exit';
        }
        
        // Function to handle back button
        function handleBack() {
            window.location.href = 'ban.php?action=back';
        }
    </script>
</body>
</html>
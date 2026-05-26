<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

// Database connection functions
function prepareStatement($query) {
    global $pdo, $conn;
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo->prepare($query);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        return $conn->prepare($query);
    } else {
        die("No valid database connection.");
    }
}

// Get user ID from URL
$uid = isset($_GET['uid']) ? $_GET['uid'] : null;

if (!$uid) {
    die("User ID is missing.");
}

// Fetch user details
$user = null;
$sql = "SELECT * FROM users WHERE uid = ?";
$stmt = prepareStatement($sql);

if ($stmt instanceof PDOStatement) {
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($stmt instanceof mysqli_stmt) {
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

if (!$user) {
    die("User not found.");
}

// Ensure proper type casting for toggle values
$user['block'] = isset($user['block']) ? (int)$user['block'] : 0;
$user['email_alert'] = isset($user['email_alert']) ? (int)$user['email_alert'] : 0;

// Get history count
$history_count = 0;
$history_sql = "SELECT COUNT(*) as count FROM history WHERE uid = ?";
$history_stmt = prepareStatement($history_sql);

if ($history_stmt instanceof PDOStatement) {
    $history_stmt->execute([$uid]);
    $history_count = $history_stmt->fetch(PDO::FETCH_ASSOC)['count'];
} elseif ($history_stmt instanceof mysqli_stmt) {
    $history_stmt->bind_param("s", $uid);
    $history_stmt->execute();
    $result = $history_stmt->get_result();
    $history_count = $result->fetch_assoc()['count'];
}

// Get beneficiary count
$beneficiary_count = 0;
$beneficiary_sql = "SELECT COUNT(*) as count FROM beneficiary WHERE uid = ?";
$beneficiary_stmt = prepareStatement($beneficiary_sql);

if ($beneficiary_stmt instanceof PDOStatement) {
    $beneficiary_stmt->execute([$uid]);
    $beneficiary_count = $beneficiary_stmt->fetch(PDO::FETCH_ASSOC)['count'];
} elseif ($beneficiary_stmt instanceof mysqli_stmt) {
    $beneficiary_stmt->bind_param("s", $uid);
    $beneficiary_stmt->execute();
    $result = $beneficiary_stmt->get_result();
    $beneficiary_count = $result->fetch_assoc()['count'];
}

// Handle form submissions
$message = "";
$message_type = ""; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_balance'])) {
        $new_balance = floatval($_POST['balance']);
        $update_sql = "UPDATE users SET balance = ? WHERE uid = ?";
        $update_stmt = prepareStatement($update_sql);
        
        if ($update_stmt instanceof PDOStatement) {
            if ($update_stmt->execute([$new_balance, $uid])) {
                $user['balance'] = $new_balance;
                $message = "Balance updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating balance!";
                $message_type = "error";
            }
        } elseif ($update_stmt instanceof mysqli_stmt) {
            $update_stmt->bind_param("ds", $new_balance, $uid);
            if ($update_stmt->execute()) {
                $user['balance'] = $new_balance;
                $message = "Balance updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating balance!";
                $message_type = "error";
            }
        }
    }
    
    if (isset($_POST['update_number'])) {
        $new_number = $_POST['number'];
        
        $check_sql = "SELECT COUNT(*) as count FROM users WHERE number = ? AND uid != ?";
        $check_stmt = prepareStatement($check_sql);
        
        if ($check_stmt instanceof PDOStatement) {
            $check_stmt->execute([$new_number, $uid]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
        } elseif ($check_stmt instanceof mysqli_stmt) {
            $check_stmt->bind_param("ss", $new_number, $uid);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            $count = $result['count'];
        }
        
        if ($count > 0) {
            $message = "Phone number already exists for another user!";
            $message_type = "error";
        } else {
            $update_sql = "UPDATE users SET number = ? WHERE uid = ?";
            $update_stmt = prepareStatement($update_sql);
            
            if ($update_stmt instanceof PDOStatement) {
                if ($update_stmt->execute([$new_number, $uid])) {
                    $user['number'] = $new_number;
                    $message = "Phone number updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating phone number!";
                    $message_type = "error";
                }
            } elseif ($update_stmt instanceof mysqli_stmt) {
                $update_stmt->bind_param("ss", $new_number, $uid);
                if ($update_stmt->execute()) {
                    $user['number'] = $new_number;
                    $message = "Phone number updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating phone number!";
                    $message_type = "error";
                }
            }
        }
    }
    
    if (isset($_POST['update_email'])) {
        $new_email = $_POST['email'];
        
        $check_sql = "SELECT COUNT(*) as count FROM users WHERE email = ? AND uid != ?";
        $check_stmt = prepareStatement($check_sql);
        
        if ($check_stmt instanceof PDOStatement) {
            $check_stmt->execute([$new_email, $uid]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
        } elseif ($check_stmt instanceof mysqli_stmt) {
            $check_stmt->bind_param("ss", $new_email, $uid);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            $count = $result['count'];
        }
        
        if ($count > 0) {
            $message = "Email already exists for another user!";
            $message_type = "error";
        } else {
            $update_sql = "UPDATE users SET email = ? WHERE uid = ?";
            $update_stmt = prepareStatement($update_sql);
            
            if ($update_stmt instanceof PDOStatement) {
                if ($update_stmt->execute([$new_email, $uid])) {
                    $user['email'] = $new_email;
                    $message = "Email updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating email!";
                    $message_type = "error";
                }
            } elseif ($update_stmt instanceof mysqli_stmt) {
                $update_stmt->bind_param("ss", $new_email, $uid);
                if ($update_stmt->execute()) {
                    $user['email'] = $new_email;
                    $message = "Email updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating email!";
                    $message_type = "error";
                }
            }
        }
    }
    
    if (isset($_POST['update_block'])) {
        $block_status = isset($_POST['block']) ? 1 : 0;
        $update_sql = "UPDATE users SET block = ? WHERE uid = ?";
        $update_stmt = prepareStatement($update_sql);
        
        if ($update_stmt instanceof PDOStatement) {
            if ($update_stmt->execute([$block_status, $uid])) {
                $user['block'] = $block_status;
                $message = "Block status updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating block status!";
                $message_type = "error";
            }
        } elseif ($update_stmt instanceof mysqli_stmt) {
            $update_stmt->bind_param("is", $block_status, $uid);
            if ($update_stmt->execute()) {
                $user['block'] = $block_status;
                $message = "Block status updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating block status!";
                $message_type = "error";
            }
        }
    }
    
    if (isset($_POST['update_plan'])) {
        if (isset($_POST['plan'])) {
            $new_plan = $_POST['plan'];
            $new_subscription_date = null;
            
            if ($new_plan === 'free') {
                $new_subscription_date = "0"; // Free plan → store 0
            } else {
                $days = 0;
                if ($new_plan === 'week') {
                    $days = 7;
                } elseif ($new_plan === 'month') {
                    $days = 30;
                } elseif ($new_plan === 'lifetime') {
                    $days = 600;
                }
                $new_subscription_date = date('d-m-Y H:i:s', strtotime("+$days days"));
            }
            
            if ($new_plan === 'free') {
                $update_sql = "UPDATE users SET plan = ?, subscription_date = '0' WHERE uid = ?";
                $params = [$new_plan, $uid];
            } else {
                $update_sql = "UPDATE users SET plan = ?, subscription_date = ? WHERE uid = ?";
                $params = [$new_plan, $new_subscription_date, $uid];
            }
            
            $update_stmt = prepareStatement($update_sql);
            
            if ($update_stmt instanceof PDOStatement) {
                if ($update_stmt->execute($params)) {
                    $user['plan'] = $new_plan;
                    $user['subscription_date'] = $new_subscription_date;
                    $message = "Subscription plan updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating subscription plan!";
                    $message_type = "error";
                }
            } elseif ($update_stmt instanceof mysqli_stmt) {
                $types = str_repeat('s', count($params));
                $update_stmt->bind_param($types, ...$params);
                if ($update_stmt->execute()) {
                    $user['plan'] = $new_plan;
                    $user['subscription_date'] = $new_subscription_date;
                    $message = "Subscription plan updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating subscription plan!";
                    $message_type = "error";
                }
            }
        } else {
            $message = "Please select a subscription plan!";
            $message_type = "error";
        }
    }
    
    if (isset($_POST['update_subscription_date'])) {
        $new_subscription_date = $_POST['subscription_date'];
        $update_sql = "UPDATE users SET subscription_date = ? WHERE uid = ?";
        $update_stmt = prepareStatement($update_sql);
        
        if ($update_stmt instanceof PDOStatement) {
            if ($update_stmt->execute([$new_subscription_date, $uid])) {
                $user['subscription_date'] = $new_subscription_date;
                $message = "Subscription date updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating subscription date!";
                $message_type = "error";
            }
        } elseif ($update_stmt instanceof mysqli_stmt) {
            $update_stmt->bind_param("ss", $new_subscription_date, $uid);
            if ($update_stmt->execute()) {
                $user['subscription_date'] = $new_subscription_date;
                $message = "Subscription date updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating subscription date!";
                $message_type = "error";
            }
        }
    }
    
    if (isset($_POST['update_email_alert'])) {
        $email_alert_status = isset($_POST['email_alert']) ? 1 : 0;
        $update_sql = "UPDATE users SET email_alert = ? WHERE uid = ?";
        $update_stmt = prepareStatement($update_sql);
        
        if ($update_stmt instanceof PDOStatement) {
            if ($update_stmt->execute([$email_alert_status, $uid])) {
                $user['email_alert'] = $email_alert_status;
                $message = "Email alert preference updated!";
                $message_type = "success";
            } else {
                $message = "Error updating email alert preference!";
                $message_type = "error";
            }
        } elseif ($update_stmt instanceof mysqli_stmt) {
            $update_stmt->bind_param("is", $email_alert_status, $uid);
            if ($update_stmt->execute()) {
                $user['email_alert'] = $email_alert_status;
                $message = "Email alert preference updated!";
                $message_type = "success";
            } else {
                $message = "Error updating email alert preference!";
                $message_type = "error";
            }
        }
    }
    
    // Handle login as user
    if (isset($_POST['login_as_user'])) {
        // Set session variable for user impersonation
        $_SESSION['user_id'] = $uid;
        $message = "You are now logged in as this user. Redirecting...";
        $message_type = "success";
        
        // JavaScript redirect after a short delay
        echo '<script>
            setTimeout(function() {
                window.location.href = "../OPay/dashboard.php";
            }, 1500);
        </script>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details | Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #6C37F2;
            --primary-light: #8C65F5;
            --primary-dark: #5A2BD9;
            --secondary: #805AD5;
            --white: #FFFFFF;
            --light-gray: #F5F7FA;
            --medium-gray: #E4E7EB;
            --dark-gray: #2D3748;
            --text: #2D3748;
            --text-light: #718096;
            --success: #38A169;
            --warning: #DD6B20;
            --danger: #E53E3E;
            --info: #3182CE;
        }
        
        body {
            background-color: var(--light-gray);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: var(--white);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            padding: 25px 0;
            height: 100vh;
            position: fixed;
            transition: all 0.3s ease;
            z-index: 100;
        }
        
        .logo {
            display: flex;
            align-items: center;
            padding: 0 25px 30px;
            border-bottom: 1px solid var(--medium-gray);
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 28px;
            color: var(--primary);
            margin-right: 12px;
        }
        
        .logo h1 {
            font-size: 24px;
            color: var(--dark-gray);
            font-weight: 700;
        }
        
        .nav-links {
            list-style: none;
            padding: 0 15px;
        }
        
        .nav-links li {
            margin-bottom: 8px;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-light);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(108, 55, 242, 0.1);
            color: var(--primary);
        }
        
        .nav-links a i {
            font-size: 20px;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h2 {
            font-size: 28px;
            color: var(--dark-gray);
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .search-bar {
            position: relative;
        }
        
        .search-bar input {
            padding: 12px 15px 12px 45px;
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            font-size: 16px;
            width: 300px;
            transition: all 0.3s ease;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 55, 242, 0.2);
        }
        
        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 18px;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn i {
            font-size: 16px;
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        .btn-warning {
            background: var(--warning);
        }
        
        .btn-warning:hover {
            background: #c05621;
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-success:hover {
            background: #2f855a;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info h3 {
            font-size: 16px;
            color: var(--dark-gray);
            font-weight: 600;
        }
        
        .user-info p {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        /* User Detail Styles */
        .user-detail-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .user-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 36px;
            margin-right: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info-container h2 {
            font-size: 28px;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }
        
        .user-id {
            color: var(--text-light);
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .user-plan {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            background: rgba(49, 130, 206, 0.1);
            color: var(--info);
            font-weight: 500;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .user-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--dark-gray);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(56, 161, 105, 0.1);
            color: var(--success);
        }
        
        .status-blocked {
            background: rgba(229, 62, 62, 0.1);
            color: var(--danger);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 55, 242, 0.2);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--success);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .plan-options {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .plan-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 25px;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .message-success {
            background: rgba(56, 161, 105, 0.1);
            color: var(--success);
            border: 1px solid rgba(56, 161, 105, 0.2);
        }
        
        .message-error {
            background: rgba(229, 62, 62, 0.1);
            color: var(--danger);
            border: 1px solid rgba(229, 62, 62, 0.2);
        }
        
        .confirmation-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .confirmation-dialog.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .confirmation-box {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .confirmation-box h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--dark-gray);
        }
        
        .confirmation-box p {
            color: var(--text-light);
            margin-bottom: 30px;
        }
        
        .confirmation-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            background: var(--success);
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 2000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .toast.error {
            background: var(--danger);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .user-detail-container {
                grid-template-columns: 1fr;
            }
            
            .search-bar input {
                width: 250px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .logo h1,
            .sidebar .nav-links a span {
                display: none;
            }
            
            .sidebar .logo {
                justify-content: center;
                padding: 0 15px 25px;
            }
            
            .sidebar .nav-links a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar .nav-links a i {
                margin-right: 0;
                font-size: 24px;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            
            .search-bar input {
                width: 100%;
            }
            
            .user-stats,
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .user-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-lock"></i>
            <h1>AdminPortal</h1>
        </div>
        <ul class="nav-links">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="user-list.php" class="active">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-wallet"></i>
                    <span>Subscriptions</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            <li>
                <a href="payment-request.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payment Requests</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2>User Details</h2>
            <div class="header-actions">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <div class="user-profile">
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
                        <p>Administrator</p>
                    </div>
                    <div class="avatar"><?php echo substr($_SESSION['admin_name'], 0, 1); ?></div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>
        
        <div class="user-detail-container">
            <div class="user-card">
                <div class="user-header">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile']); ?>" alt="Profile">
                        <?php else: 
                            $nameParts = explode(' ', $user['name']);
                            $initials = '';
                            foreach ($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            $initials = substr($initials, 0, 2);
                            echo $initials;
                        endif; ?>
                    </div>
                    <div class="user-info-container">
                        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                        <div class="user-id">ID: <?php echo htmlspecialchars($user['uid']); ?></div>
                        <span class="user-plan"><?php echo htmlspecialchars(ucfirst($user['plan'])); ?> Plan</span>
                    </div>
                </div>
                
                <div class="user-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($user['balance'], 2); ?></div>
                        <div class="stat-label">Balance</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($user['amount_in'], 2); ?></div>
                        <div class="stat-label">Total In</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($user['amount_out'], 2); ?></div>
                        <div class="stat-label">Total Out</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo htmlspecialchars($user['device'] ?? 'N/A'); ?></div>
                        <div class="stat-label">Device</div>
                    </div>
                </div>
                
                <div class="user-details">
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Phone Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['number']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Registration Date</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['date']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Last Login</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['last_login']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Android ID</div>
                        <div class="detail-value"><?php echo htmlspecialchars($user['android_id'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Subscription Date</div>
                        <div class="detail-value">
                            <?php 
                                if (!empty($user['subscription_date']) && $user['subscription_date'] !== '0') {
                                    echo htmlspecialchars($user['subscription_date']);
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <?php if ($user['block']): ?>
                                <span class="status-badge status-blocked">Blocked</span>
                            <?php else: ?>
                                <span class="status-badge status-active">Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="user-card">
                <h3 style="margin-bottom: 25px;">Manage User</h3>
                
                <!-- Login as User Form -->
                <form method="POST">
                    <button type="submit" name="login_as_user" class="btn btn-success" style="margin-bottom: 30px; width: 100%;">
                        <i class="fas fa-user"></i> Login as This User
                    </button>
                </form>
                
                <!-- Balance Form -->
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Balance</label>
                        <input type="number" step="0.01" class="form-input" name="balance" value="<?php echo $user['balance']; ?>" required>
                    </div>
                    <button type="submit" name="update_balance" class="btn">Update Balance</button>
                </form>
                
                <div style="height: 1px; background: var(--medium-gray); margin: 30px 0;"></div>
                
                <!-- Phone Number Form -->
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-input" name="number" value="<?php echo htmlspecialchars($user['number']); ?>" required>
                    </div>
                    <button type="submit" name="update_number" class="btn">Update Phone Number</button>
                </form>
                
                <div style="height: 1px; background: var(--medium-gray); margin: 30px 0;"></div>
                
                <!-- Email Form -->
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" name="update_email" class="btn">Update Email</button>
                </form>
                
                <div style="height: 1px; background: var(--medium-gray); margin: 30px 0;"></div>
                
                <!-- Block Toggle -->
                <form method="POST" id="blockForm">
                    <div class="form-group">
                        <div class="toggle-label">
                            <span class="form-label">Block User</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="block" id="blockToggle" 
                                    <?php echo $user['block'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="update_block" value="1">
                </form>
                
                <div style="height: 1px; background: var(--medium-gray); margin: 30px 0;"></div>
                
                <!-- Subscription Plan -->
                <form method="POST">
                    <label class="form-label">Subscription Plan</label>
                    <div class="plan-options">
                        <label class="plan-option">
                            <input type="radio" name="plan" value="free" <?php echo $user['plan'] === 'free' ? 'checked' : ''; ?>>
                            Free
                        </label>
                        <label class="plan-option">
                            <input type="radio" name="plan" value="week" <?php echo $user['plan'] === 'week' ? 'checked' : ''; ?>>
                            Weekly
                        </label>
                        <label class="plan-option">
                            <input type="radio" name="plan" value="month" <?php echo $user['plan'] === 'month' ? 'checked' : ''; ?>>
                            Monthly
                        </label>
                        <label class="plan-option">
                            <input type="radio" name="plan" value="lifetime" <?php echo $user['plan'] === 'lifetime' ? 'checked' : ''; ?>>
                            Lifetime
                        </label>
                    </div>
                    <button type="submit" name="update_plan" class="btn" style="margin-top: 15px;">Update Subscription</button>
                </form>
                
                <div style="height: 1px; background: var(--medium-gray); margin: 30px 0;"></div>
                
                <!-- Subscription Date Form -->
<form method="POST">
    <div class="form-group">
        <label class="form-label">Subscription Date (Manual Override)</label>
        <input type="text" class="form-input" name="subscription_date" 
            value="<?php 
                if (!empty($user['subscription_date']) && $user['subscription_date'] !== '0') {
                    echo htmlspecialchars($user['subscription_date']);
                }
            ?>" placeholder="Format: d-m-Y H:i:s (e.g., 25-12-2023 14:30:00)">
        <small style="color: var(--text-light); font-size: 12px; display: block; margin-top: 5px;">
            Format: day-month-year hour:minute:second (e.g., 25-12-2023 14:30:00)
        </small>
    </div>
    <button type="submit" name="update_subscription_date" class="btn">Update Subscription Date</button>
</form>
                
                <div style="height: 1px; background: var(--medium-gray); margin: 30px 0;"></div>
                
                <!-- Email Alert Toggle -->
                <form method="POST" id="emailAlertForm">
                    <div class="form-group">
                        <div class="toggle-label">
                            <span class="form-label">Email Alerts</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_alert" id="emailAlertToggle" 
                                    <?php echo $user['email_alert'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="update_email_alert" value="1">
                </form>
            </div>
        </div>
        
        <div class="user-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3>User Data</h3>
                <div style="display: flex; gap: 15px;">
                    <div class="stat-card" style="margin: 0;">
                        <div class="stat-value" id="historyCount"><?php echo $history_count; ?></div>
                        <div class="stat-label">Transaction History</div>
                    </div>
                    <div class="stat-card" style="margin: 0;">
                        <div class="stat-value" id="beneficiaryCount"><?php echo $beneficiary_count; ?></div>
                        <div class="stat-label">Beneficiaries</div>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-warning" id="clearHistoryBtn">
                    <i class="fas fa-history"></i> Clear Transaction History
                </button>
                
                <button class="btn btn-warning" id="clearBeneficiaryBtn">
                    <i class="fas fa-users"></i> Clear Beneficiary List
                </button>
                
                <button class="btn">
                    <i class="fas fa-file-alt"></i> View Transaction History
                </button>
                
                <button class="btn">
                    <i class="fas fa-user-friends"></i> View Beneficiaries
                </button>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Dialog -->
    <div class="confirmation-dialog" id="confirmationDialog">
        <div class="confirmation-box">
            <h3>Confirm Action</h3>
            <p id="confirmationMessage">Are you sure you want to perform this action? This cannot be undone.</p>
            <div class="confirmation-buttons">
                <button class="btn" id="cancelButton">Cancel</button>
                <button class="btn btn-danger" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toastNotification">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage">Operation successful!</span>
    </div>
    
    <script>
        // Toast notification function
        function showToast(message, isError = false) {
            const toast = document.getElementById('toastNotification');
            const messageEl = document.getElementById('toastMessage');
            
            messageEl.textContent = message;
            toast.classList.remove('error');
            
            if (isError) {
                toast.classList.add('error');
                toast.querySelector('i').className = 'fas fa-exclamation-circle';
            } else {
                toast.querySelector('i').className = 'fas fa-check-circle';
            }
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Confirmation dialog for destructive actions
        document.addEventListener('DOMContentLoaded', function() {
            const confirmationDialog = document.getElementById('confirmationDialog');
            const confirmationMessage = document.getElementById('confirmationMessage');
            const cancelButton = document.getElementById('cancelButton');
            const confirmButton = document.getElementById('confirmButton');
            const historyCountEl = document.getElementById('historyCount');
            const beneficiaryCountEl = document.getElementById('beneficiaryCount');
            
            let currentAction = null;
            const uid = '<?php echo $uid; ?>';
            
            // Toggle change event for Block
            document.getElementById('blockToggle').addEventListener('change', function() {
                document.getElementById('blockForm').submit();
            });
            
            // Toggle change event for Email Alert
            document.getElementById('emailAlertToggle').addEventListener('change', function() {
                document.getElementById('emailAlertForm').submit();
            });
            
            // Clear History Button
            document.getElementById('clearHistoryBtn').addEventListener('click', function() {
                confirmationMessage.textContent = "Are you sure you want to clear ALL transaction history for this user? This action cannot be undone.";
                currentAction = 'history';
                confirmationDialog.classList.add('active');
            });
            
            // Clear Beneficiary Button
            document.getElementById('clearBeneficiaryBtn').addEventListener('click', function() {
                confirmationMessage.textContent = "Are you sure you want to clear ALL beneficiaries for this user? This action cannot be undone.";
                currentAction = 'beneficiary';
                confirmationDialog.classList.add('active');
            });
            
            // Cancel button
            cancelButton.addEventListener('click', function() {
                confirmationDialog.classList.remove('active');
                currentAction = null;
            });
            
            // Confirm button
            confirmButton.addEventListener('click', function() {
                if (!currentAction) return;
                
                confirmationDialog.classList.remove('active');
                
                // Determine endpoint based on action
                const endpoint = currentAction === 'history' 
                    ? 'clear_history.php' 
                    : 'clear_beneficiary.php';
                
                // Send AJAX request
                const formData = new FormData();
                formData.append('uid', uid);
                
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update count on page
                        if (currentAction === 'history') {
                            historyCountEl.textContent = data.count;
                        } else {
                            beneficiaryCountEl.textContent = data.count;
                        }
                        
                        // Show success toast
                        showToast(data.message);
                    } else {
                        showToast(data.message, true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred. Please try again.', true);
                });
                
                currentAction = null;
            });
        });
    </script>
</body>
</html>
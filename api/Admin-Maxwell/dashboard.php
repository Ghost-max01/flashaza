<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Database configuration
require_once 'config.php';

// Function to prepare a statement for either PDO or MySQLi
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

// Function to run a query for either PDO or MySQLi
function runQuery($query) {
    global $pdo, $conn;
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo->query($query);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        return $conn->query($query);
    } else {
        die("No valid database connection.");
    }
}

// Handle maintenance mode toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_maintenance'])) {
    $maintenance_status = $_POST['maintenance_status'] === 'true' ? 1 : 0;
    $stmt = prepareStatement("UPDATE maintenance SET is_maintenance = ? WHERE id = 1");

    if ($stmt instanceof PDOStatement) {
        $stmt->execute([$maintenance_status]);
    } else {
        $stmt->bind_param("i", $maintenance_status);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle bank details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bank'])) {
    $bank_name       = $_POST['bank_name'];
    $account_name    = $_POST['account_name'];
    $account_number  = $_POST['account_number'];

    $stmt = prepareStatement("UPDATE bank_details SET bank_name = ?, account_name = ?, account_number = ? WHERE id = 1");

    if ($stmt instanceof PDOStatement) {
        $stmt->execute([$bank_name, $account_name, $account_number]);
    } else {
        $stmt->bind_param("sss", $bank_name, $account_name, $account_number);
        $stmt->execute();
        $stmt->close();
    }
}

// Get all statistics
function getStatCount($sql) {
    $result = runQuery($sql);
    if ($result instanceof PDOStatement) {
        return (int) $result->fetchColumn();
    } else {
        $row = $result->fetch_row();
        return (int) $row[0];
    }
}

// Fetch statistics
$total_users           = getStatCount("SELECT COUNT(*) FROM users");
$blocked_users         = getStatCount("SELECT COUNT(*) FROM users WHERE block = 1");
$weekly_subscribers    = getStatCount("SELECT COUNT(*) FROM users WHERE plan = 'week'");
$monthly_subscribers   = getStatCount("SELECT COUNT(*) FROM users WHERE plan = 'month'");
$lifetime_subscribers  = getStatCount("SELECT COUNT(*) FROM users WHERE plan = 'lifetime'");
$pending_payments      = getStatCount("SELECT COUNT(*) FROM payment_requests WHERE status = 'pending'");

// Fetch maintenance status
$maintenance_result = runQuery("SELECT is_maintenance FROM maintenance WHERE id = 1");
if ($maintenance_result instanceof PDOStatement) {
    $is_maintenance = $maintenance_result->fetchColumn();
} else {
    $maintenance_row = $maintenance_result->fetch_assoc();
    $is_maintenance  = $maintenance_row['is_maintenance'];
}

// Fetch bank details
$bank_result = runQuery("SELECT * FROM bank_details WHERE id = 1");
if ($bank_result instanceof PDOStatement) {
    $bank_details = $bank_result->fetch(PDO::FETCH_ASSOC);
} else {
    $bank_details = $bank_result->fetch_assoc();
}

// Fetch recent notifications
$notifications = runQuery("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 4");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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

        .nav-links a:hover, .nav-links a.active {
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(108, 55, 242, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary);
        }

        .stat-card:nth-child(2)::before { background: var(--success); }
        .stat-card:nth-child(3)::before { background: var(--warning); }
        .stat-card:nth-child(4)::before { background: var(--info); }
        .stat-card:nth-child(5)::before { background: var(--danger); }
        .stat-card:nth-child(6)::before { background: var(--secondary); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            background: rgba(108, 55, 242, 0.1);
            color: var(--primary);
        }

        .stat-card:nth-child(2) .stat-icon { background: rgba(56, 161, 105, 0.1); color: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(221, 107, 32, 0.1); color: var(--warning); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(49, 130, 206, 0.1); color: var(--info); }
        .stat-card:nth-child(5) .stat-icon { background: rgba(229, 62, 62, 0.1); color: var(--danger); }
        .stat-card:nth-child(6) .stat-icon { background: rgba(128, 90, 213, 0.1); color: var(--secondary); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 5px;
            transition: all 0.5s ease;
        }

        .stat-title {
            font-size: 16px;
            color: var(--text-light);
            font-weight: 500;
        }

        /* Dashboard Sections */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h3 {
            font-size: 20px;
            color: var(--dark-gray);
            font-weight: 600;
        }

        .card-header .header-actions {
            display: flex;
            gap: 15px;
        }

        .card-header i {
            font-size: 20px;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .card-header i:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        /* Maintenance Toggle */
        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 0;
            border-bottom: 1px solid var(--medium-gray);
        }

        .toggle-container:last-child {
            border: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toggle-label i {
            font-size: 24px;
            color: var(--warning);
        }

        .toggle-text h4 {
            font-size: 16px;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .toggle-text p {
            font-size: 14px;
            color: var(--text-light);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
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
            background-color: var(--medium-gray);
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
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
            transform: translateX(30px);
        }

        /* Bank Details */
        .bank-details {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }

        .detail-row:last-child {
            border: none;
        }

        .detail-label {
            font-size: 14px;
            color: var(--text-light);
        }

        .detail-value {
            font-size: 15px;
            color: var(--dark-gray);
            font-weight: 500;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Notifications */
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-radius: 12px;
            background: var(--light-gray);
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            transform: translateX(5px);
            background: rgba(108, 55, 242, 0.05);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(56, 161, 105, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--success);
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h4 {
            font-size: 15px;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .notification-content p {
            font-size: 14px;
            color: var(--text-light);
        }

        .notification-time {
            font-size: 12px;
            color: var(--text-light);
            align-self: flex-start;
        }

        /* Subscription Button */
        .subscription-card {
            grid-column: span 2;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 16px;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(108, 55, 242, 0.3);
            color: white;
            overflow: hidden;
            position: relative;
        }

        .subscription-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .subscription-text h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .subscription-text p {
            font-size: 16px;
            opacity: 0.9;
            max-width: 500px;
        }

        .subscription-btn {
            background: white;
            color: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            display: inline-block;
        }

        .subscription-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Payment Requests Section */
        .payment-requests-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            grid-column: span 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .payment-requests-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .payment-requests-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .payment-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            background: rgba(128, 90, 213, 0.1);
            color: var(--secondary);
        }

        .payment-text h3 {
            font-size: 22px;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .payment-text p {
            font-size: 16px;
            color: var(--text-light);
        }

        .payment-arrow {
            font-size: 24px;
            color: var(--secondary);
        }

        /* Package Plan Management */
        .package-plan-card {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            grid-column: span 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .package-plan-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .package-plan-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .package-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            background: rgba(49, 130, 206, 0.1);
            color: var(--info);
        }

        .package-text h3 {
            font-size: 22px;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .package-text p {
            font-size: 16px;
            color: var(--text-light);
        }

        .package-arrow {
            font-size: 24px;
            color: var(--info);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            padding: 30px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            color: var(--primary);
            transform: rotate(90deg);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 24px;
            color: var(--dark-gray);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-gray);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 55, 242, 0.2);
        }

        .submit-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px 20px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animated-card {
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .subscription-card, 
            .payment-requests-card,
            .package-plan-card {
                grid-column: span 1;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .menu-toggle {
                display: block;
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
                <a href="#" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="subscriptions.php">
                    <i class="fas fa-wallet"></i>
                    <span>Subscriptions</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="analytics.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li>
                <a href="notifications.php">
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
            <h2>Dashboard Overview</h2>
            <div class="user-profile">
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
                    <p>Administrator</p>
                </div>
                <div class="avatar"><?php echo substr($_SESSION['admin_name'], 0, 1); ?></div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card animated-card" onclick="window.location.href='user-list.php?type=all'">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value" id="total-users"><?php echo $total_users; ?></div>
                <div class="stat-title">Total Users</div>
            </div>
            
            <div class="stat-card animated-card" onclick="window.location.href='user-list.php?type=blocked'">
                <div class="stat-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-value" id="blocked-users"><?php echo $blocked_users; ?></div>
                <div class="stat-title">Blocked Users</div>
            </div>
            
            <div class="stat-card animated-card" onclick="window.location.href='user-list.php?type=week'">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-value" id="weekly-subscribers"><?php echo $weekly_subscribers; ?></div>
                <div class="stat-title">Weekly Subscribers</div>
            </div>
            
            <div class="stat-card animated-card" onclick="window.location.href='user-list.php?type=month'">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value" id="monthly-subscribers"><?php echo $monthly_subscribers; ?></div>
                <div class="stat-title">Monthly Subscribers</div>
            </div>
            
            <div class="stat-card animated-card" onclick="window.location.href='user-list.php?type=lifetime'">
                <div class="stat-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-value" id="lifetime-subscribers"><?php echo $lifetime_subscribers; ?></div>
                <div class="stat-title">Lifetime Subscribers</div>
            </div>
            
            <!-- Payment Requests Card -->
            <div class="stat-card animated-card" onclick="window.location.href='payment-request.php'">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value" id="pending-payments"><?php echo $pending_payments; ?></div>
                <div class="stat-title">Pending Payments</div>
            </div>
        </div>
        
        <!-- Dashboard Sections -->
        <div class="dashboard-sections">
            <!-- App Maintenance -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>App Maintenance</h3>
                    <div class="header-actions">
                        <i class="fas fa-sync-alt" title="Refresh Status"></i>
                    </div>
                </div>
                <form method="POST" action="dashboard.php">
                    <div class="toggle-container">
                        <div class="toggle-label">
                            <i class="fas fa-tools"></i>
                            <div class="toggle-text">
                                <h4>Maintenance Mode</h4>
                                <p>Temporarily disable access to the application</p>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="maintenance_status" 
                                value="true" <?php echo $is_maintenance ? 'checked' : ''; ?> 
                                onchange="this.form.submit()">
                            <span class="slider"></span>
                            <input type="hidden" name="toggle_maintenance" value="1">
                        </label>
                    </div>
                </form>
                <div class="toggle-container">
                    <div class="toggle-label">
                        <i class="fas fa-download"></i>
                        <div class="toggle-text">
                            <h4>App Updates</h4>
                            <p>Manage application updates</p>
                        </div>
                    </div>
                    <a href="app-update.php" class="btn">Go to Updates</a>
                </div>
                <div class="toggle-container">
                    <div class="toggle-label">
                        <i class="fas fa-server"></i>
                        <div class="toggle-text">
                            <h4>Server Monitoring</h4>
                            <p>Monitor server performance in real-time</p>
                        </div>
                    </div>
                    <a href="server-monitoring.php" class="btn">View Monitoring</a>
                </div>
            </div>
            
            <!-- Bank Details -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Bank Account Details</h3>
                    <div class="header-actions">
                        <i class="fas fa-edit" onclick="openBankModal()" title="Edit Bank Details"></i>
                    </div>
                </div>
                <div class="bank-details">
                    <div class="detail-row">
                        <div class="detail-label">Bank Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($bank_details['bank_name']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Account Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($bank_details['account_name']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Account Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($bank_details['account_number']); ?></div>
                    </div>
                    <button class="btn" onclick="openBankModal()">
                        <i class="fas fa-edit"></i> Update Bank Details
                    </button>
                </div>
            </div>
            
            <!-- Notifications -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Notifications</h3>
                    <div class="header-actions">
                        <a href="notification-history.php" title="View All Notifications">
                            <i class="fas fa-history"></i>
                        </a>
                    </div>
                </div>
                <div class="notification-list">
                    <?php 
                    if ($notifications && $notifications->num_rows > 0):
                        while ($notification = $notifications->fetch_assoc()): 
                    ?>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="<?php echo htmlspecialchars($notification['icon']); ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        </div>
                        <div class="notification-time">
                            <?php 
                                $date = new DateTime($notification['created_at']);
                                echo $date->format('M d, H:i');
                            ?>
                        </div>
                    </div>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                    <div class="notification-item">
                        <div class="notification-content">
                            <h4>No Recent Notifications</h4>
                            <p>You have no new notifications at this time</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Requests Section -->
            <div class="payment-requests-card" onclick="window.location.href='payment-request.php'">
                <div class="payment-requests-content">
                    <div class="payment-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="payment-text">
                        <h3>Payment Requests</h3>
                        <p>View and manage pending payment requests from users</p>
                    </div>
                </div>
                <div class="payment-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
            
            <!-- Package Plan Management -->
            <div class="package-plan-card" onclick="window.location.href='subscription-plan.php'">
                <div class="package-plan-content">
                    <div class="package-icon">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="package-text">
                        <h3>Package Plan Management</h3>
                        <p>Create, edit, and manage subscription packages and pricing</p>
                    </div>
                </div>
                <div class="package-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
            
            <!-- Subscription Button -->
            <div class="subscription-card">
                <div class="subscription-text">
                    <h3>Manage Subscription Plans</h3>
                    <p>Update pricing, features, and create new subscription tiers for your users</p>
                </div>
                <a href="subscription-plan.php" class="subscription-btn">
                    <i class="fas fa-arrow-right"></i> Go to Pricing
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bank Details Modal -->
    <div class="modal" id="bankModal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeBankModal()">&times;</span>
            <div class="modal-header">
                <h3>Update Bank Details</h3>
            </div>
            <form method="POST" action="dashboard.php">
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <input type="text" id="bank_name" name="bank_name" class="form-control" 
                        value="<?php echo htmlspecialchars($bank_details['bank_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="account_name">Account Name</label>
                    <input type="text" id="account_name" name="account_name" class="form-control" 
                        value="<?php echo htmlspecialchars($bank_details['account_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="account_number">Account Number</label>
                    <input type="text" id="account_number" name="account_number" class="form-control" 
                        value="<?php echo htmlspecialchars($bank_details['account_number']); ?>" required>
                </div>
                <button type="submit" name="update_bank" class="submit-btn">Update Bank Details</button>
            </form>
        </div>
    </div>
    
    <script>
        // Animated counter for stats
        function animateCounter(element, finalValue, duration = 2000) {
            let start = 0;
            const increment = finalValue / (duration / 16);
            const counter = () => {
                start += increment;
                if (start < finalValue) {
                    element.textContent = Math.floor(start).toLocaleString();
                    requestAnimationFrame(counter);
                } else {
                    element.textContent = finalValue.toLocaleString();
                }
            };
            counter();
        }
        
        // Initialize counters
        document.addEventListener('DOMContentLoaded', function() {
            // Animate the counters if they are not zero
            const totalUsers = document.getElementById('total-users');
            if (parseInt(totalUsers.textContent) > 0) {
                animateCounter(totalUsers, <?php echo $total_users; ?>);
            }
            
            const blockedUsers = document.getElementById('blocked-users');
            if (parseInt(blockedUsers.textContent) > 0) {
                animateCounter(blockedUsers, <?php echo $blocked_users; ?>);
            }
            
            const weeklySubs = document.getElementById('weekly-subscribers');
            if (parseInt(weeklySubs.textContent) > 0) {
                animateCounter(weeklySubs, <?php echo $weekly_subscribers; ?>);
            }
            
            const monthlySubs = document.getElementById('monthly-subscribers');
            if (parseInt(monthlySubs.textContent) > 0) {
                animateCounter(monthlySubs, <?php echo $monthly_subscribers; ?>);
            }
            
            const lifetimeSubs = document.getElementById('lifetime-subscribers');
            if (parseInt(lifetimeSubs.textContent) > 0) {
                animateCounter(lifetimeSubs, <?php echo $lifetime_subscribers; ?>);
            }
            
            const pendingPayments = document.getElementById('pending-payments');
            if (parseInt(pendingPayments.textContent) > 0) {
                animateCounter(pendingPayments, <?php echo $pending_payments; ?>);
            }
            
            // Add hover effect to cards
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-10px)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Modal functions
        function openBankModal() {
            console.log("Opening bank modal");
            document.getElementById('bankModal').classList.add('active');
        }
        
        function closeBankModal() {
            document.getElementById('bankModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bankModal');
            if (event.target == modal) {
                closeBankModal();
            }
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeBankModal();
            }
        });
    </script>
</body>
</html> 
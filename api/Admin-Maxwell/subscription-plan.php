<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: index.php");
    exit();
}
require_once 'config.php';

// Database connection function
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

// Fetch current prices
$prices = [
    'weekly' => '0.00',
    'monthly' => '0.00',
    'lifetime' => '0.00'
];

$sql = "SELECT type, price FROM price";
$stmt = prepareStatement($sql);

if ($stmt) {
    if ($stmt instanceof PDOStatement) {
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($stmt instanceof mysqli_stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    foreach ($results as $row) {
        $prices[$row['type']] = number_format($row['price'], 2);
    }
}

// Handle form submissions
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_weekly'])) {
        $new_price = floatval($_POST['weekly_price']);
        updatePrice('weekly', $new_price);
    } 
    elseif (isset($_POST['update_monthly'])) {
        $new_price = floatval($_POST['monthly_price']);
        updatePrice('monthly', $new_price);
    } 
    elseif (isset($_POST['update_lifetime'])) {
        $new_price = floatval($_POST['lifetime_price']);
        updatePrice('lifetime', $new_price);
    }
}

function updatePrice($plan_type, $price) {
    global $message, $message_type;
    
    if ($price <= 0) {
        $message = "Price must be greater than zero!";
        $message_type = "error";
        return;
    }
    
    $sql = "UPDATE price SET price = ? WHERE type = ?";
    $stmt = prepareStatement($sql);
    
    try {
        if ($stmt instanceof PDOStatement) {
            $success = $stmt->execute([$price, $plan_type]);
            $rowCount = $stmt->rowCount();
        } elseif ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param("ds", $price, $plan_type);
            $success = $stmt->execute();
            $rowCount = $stmt->affected_rows;
        }
        
        if ($success && $rowCount > 0) {
            $message = ucfirst($plan_type) . " price updated successfully!";
            $message_type = "success";
            $prices[$plan_type] = number_format($price, 2);
        } else {
            $message = "No changes made to " . ucfirst($plan_type) . " price";
            $message_type = "info";
        }
    } catch (Exception $e) {
        $message = "Error updating price: " . $e->getMessage();
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Pricing | Admin Portal</title>
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
        
        /* Message Styles */
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
        
        .message-info {
            background: rgba(49, 130, 206, 0.1);
            color: var(--info);
            border: 1px solid rgba(49, 130, 206, 0.2);
        }
        
        /* Pricing Container */
        .pricing-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 30px;
        }
        
        @media (max-width: 992px) {
            .pricing-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .pricing-container {
                grid-template-columns: 1fr;
            }
        }
        
        .pricing-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 2px solid var(--medium-gray);
        }
        
        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .pricing-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .pricing-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .pricing-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .weekly .pricing-icon { color: var(--info); }
        .monthly .pricing-icon { color: var(--success); }
        .lifetime .pricing-icon { color: var(--warning); }
        
        .pricing-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .price-input {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .currency-symbol {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-gray);
        }
        
        .form-input-price {
            flex: 1;
            padding: 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .form-input-price:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 55, 242, 0.2);
        }
        
        .update-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .update-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .weekly .update-btn { background: var(--info); }
        .monthly .update-btn { background: var(--success); }
        .lifetime .update-btn { background: var(--warning); }
        
        .weekly .update-btn:hover { background: #2b6cb0; }
        .monthly .update-btn:hover { background: #2f855a; }
        .lifetime .update-btn:hover { background: #c05621; }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .search-bar input {
                width: 250px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            .sidebar .logo h1, .sidebar .nav-links a span {
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
                <a href="user-list.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="#" class="active">
                    <i class="fas fa-wallet"></i>
                    <span>Subscriptions</span>
                </a>
            </li>
            <li>
                <a href="subscription-pricing.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Pricing</span>
                </a>
            </li>
            <li>
                <a href="payment-request.php">
                    <i class="fas fa-money-check"></i>
                    <span>Payment Requests</span>
                </a>
            </li>
            <li>
                <a href="#">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
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
            <h2>Subscription Pricing</h2>
            <div class="header-actions">
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
            <i class="fas <?php 
                if ($message_type === 'success') echo 'fa-check-circle';
                elseif ($message_type === 'error') echo 'fa-exclamation-circle';
                else echo 'fa-info-circle';
            ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>
        
        <div class="pricing-container">
            <!-- Weekly Pricing Card -->
            <div class="pricing-card weekly">
                <div class="pricing-header">
                    <div class="pricing-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <h3 class="pricing-title">Weekly Plan</h3>
                </div>
                <form method="POST" class="pricing-form">
                    <div class="price-input">
                        <span class="currency-symbol">₦</span>
                        <input type="number" step="0.01" min="1" class="form-input-price" 
                               name="weekly_price" value="<?php echo $prices['weekly']; ?>" required>
                    </div>
                    <button type="submit" name="update_weekly" class="update-btn">
                        <i class="fas fa-sync-alt"></i> Update Price
                    </button>
                </form>
            </div>
            
            <!-- Monthly Pricing Card -->
            <div class="pricing-card monthly">
                <div class="pricing-header">
                    <div class="pricing-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="pricing-title">Monthly Plan</h3>
                </div>
                <form method="POST" class="pricing-form">
                    <div class="price-input">
                        <span class="currency-symbol">₦</span>
                        <input type="number" step="0.01" min="1" class="form-input-price" 
                               name="monthly_price" value="<?php echo $prices['monthly']; ?>" required>
                    </div>
                    <button type="submit" name="update_monthly" class="update-btn">
                        <i class="fas fa-sync-alt"></i> Update Price
                    </button>
                </form>
            </div>
            
            <!-- Lifetime Pricing Card -->
            <div class="pricing-card lifetime">
                <div class="pricing-header">
                    <div class="pricing-icon">
                        <i class="fas fa-infinity"></i>
                    </div>
                    <h3 class="pricing-title">Lifetime Plan</h3>
                </div>
                <form method="POST" class="pricing-form">
                    <div class="price-input">
                        <span class="currency-symbol">₦</span>
                        <input type="number" step="0.01" min="1" class="form-input-price" 
                               name="lifetime_price" value="<?php echo $prices['lifetime']; ?>" required>
                    </div>
                    <button type="submit" name="update_lifetime" class="update-btn">
                        <i class="fas fa-sync-alt"></i> Update Price
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-format price inputs
        document.querySelectorAll('.form-input-price').forEach(input => {
            input.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            });
            
            input.addEventListener('focus', function() {
                this.select();
            });
        });
    </script>
</body>
</html>
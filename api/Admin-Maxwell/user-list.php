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

// Determine filter from URL
$filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$page_title = "All Users";

// Build query based on filter
$sql = "SELECT * FROM users";
$params = [];

switch ($filter) {
    case 'blocked':
        $sql .= " WHERE block = 1";
        $page_title = "Blocked Users";
        break;
    case 'week':
        $sql .= " WHERE plan = 'week'";
        $page_title = "Weekly Subscribers";
        break;
    case 'month':
        $sql .= " WHERE plan = 'month'";
        $page_title = "Monthly Subscribers";
        break;
    case 'lifetime':
        $sql .= " WHERE plan = 'lifetime'";
        $page_title = "Lifetime Subscribers";
        break;
    default:
        // Show all users
        break;
}

// Execute the query
$result = runQuery($sql);
$user_list = [];
$user_count = 0;

if ($result) {
    if ($result instanceof PDOStatement) {
        $user_list = $result->fetchAll(PDO::FETCH_ASSOC);
        $user_count = count($user_list);
    } else {
        $user_count = $result->num_rows;
        while ($user = $result->fetch_assoc()) {
            $user_list[] = $user;
        }
    }
} else {
    die("Error executing query: " . (isset($conn) ? $conn->error : "PDO error"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Admin Portal</title>
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
            overflow: hidden; /* Ensures images stay within circle */
    position: relative;
}
        }

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border-radius: 30px;
            background: var(--white);
            border: 1px solid var(--medium-gray);
            color: var(--text-light);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-tab:hover, .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* User List Styles */
        .user-list-container {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .list-header h3 {
            font-size: 22px;
            color: var(--dark-gray);
        }

        .user-count {
            background: var(--primary-light);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th {
            text-align: left;
            padding: 15px 20px;
            border-bottom: 2px solid var(--medium-gray);
            color: var(--text-light);
            font-weight: 600;
            font-size: 14px;
        }

        .user-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--medium-gray);
            color: var(--text);
            font-size: 15px;
        }

        .user-table tr:last-child td {
            border-bottom: none;
        }

        .user-table tr:hover {
            background: rgba(108, 55, 242, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .password-cell {
            font-family: monospace;
            letter-spacing: 1px;
        }

        .view-btn {
            background: var(--primary-light);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .view-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
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

        .status-pending {
            background: rgba(221, 107, 32, 0.1);
            color: var(--warning);
        }

        .plan-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background: rgba(49, 130, 206, 0.1);
            color: var(--info);
        }

        /* Responsive */
        @media (max-width: 1200px) {
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

            .user-table {
                display: block;
                overflow-x: auto;
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
            
            .user-table th, 
            .user-table td {
                padding: 12px 15px;
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
            <h2>User Management</h2>
            <div class="header-actions">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search users...">
                </div>
                <button class="btn">
                    <i class="fas fa-plus"></i> Add User
                </button>
                <div class="user-profile">
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h3>
                        <p>Administrator</p>
                    </div>
                    <div class="avatar"><?php echo substr($_SESSION['admin_name'], 0, 1); ?></div>
                </div>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="user-list.php?type=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All Users</a>
            <a href="user-list.php?type=blocked" class="filter-tab <?php echo $filter === 'blocked' ? 'active' : ''; ?>">Blocked Users</a>
            <a href="user-list.php?type=week" class="filter-tab <?php echo $filter === 'week' ? 'active' : ''; ?>">Weekly Subscribers</a>
            <a href="user-list.php?type=month" class="filter-tab <?php echo $filter === 'month' ? 'active' : ''; ?>">Monthly Subscribers</a>
            <a href="user-list.php?type=lifetime" class="filter-tab <?php echo $filter === 'lifetime' ? 'active' : ''; ?>">Lifetime Subscribers</a>
        </div>
        
        <div class="user-list-container">
            <div class="list-header">
                <h3><?php echo $page_title; ?></h3>
                <div class="user-count"><?php echo $user_count; ?> Users</div>
            </div>
            
            <table class="user-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Phone Number</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user_count > 0): ?>
                        <?php foreach ($user_list as $user): ?>
                        <tr>
                            <!-- ... existing code ... -->
<td>
    <div class="user-info-cell">
        <div class="user-avatar">
            <?php 
            $nameParts = explode(' ', $user['name']);
            $initials = '';
            foreach ($nameParts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
            $initials = substr($initials, 0, 2);
            
            if (!empty($user['profile'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile']); ?>" 
                     alt="Profile" 
                     style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <?php echo $initials; ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <div>ID: <?php echo htmlspecialchars($user['id']); ?></div>
        </div>
    </div>
</td>
<!-- ... existing code ... -->
                            <td><?php echo htmlspecialchars($user['number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="password-cell">••••••••</td>
                            <td><span class="plan-badge"><?php 
                                $plan = $user['plan'] ?? 'free';
                                echo htmlspecialchars(ucfirst($plan)); 
                            ?></span></td>
                            <td>
                                <?php if (($user['block'] ?? 0) == 1): ?>
                                    <span class="status-badge status-blocked">Blocked</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="user-detail.php?uid=<?php echo $user['uid']; ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <div style="font-size: 18px; color: var(--text-light);">
                                    <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 15px; color: var(--medium-gray);"></i>
                                    <p>No users found matching your criteria</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.user-table tbody tr');
            
            rows.forEach(row => {
                if (row.querySelector('.user-name')) {
                    const name = row.querySelector('.user-name').textContent.toLowerCase();
                    const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const phone = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const userId = row.querySelector('td:nth-child(1) div:nth-child(2) div:last-child').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || email.includes(searchTerm) || 
                        phone.includes(searchTerm) || userId.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
        
        // Filter tabs active state
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
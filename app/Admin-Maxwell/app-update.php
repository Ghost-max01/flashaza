<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header("Location: index.php");
    exit();
}

// Database configuration
require_once 'config.php';

// Handle form submission
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $version = $_POST['version'];
    $message_text = $_POST['message'];
    $url = $_POST['url'];
    $action = $_POST['action'];
    
    if ($action === "add") {
        try {
            // Insert new update
            $stmt = $pdo->prepare("INSERT INTO app_updates (version, message, url) VALUES (?, ?, ?)");
            $stmt->execute([$version, $message_text, $url]);
            
            $message = "App update added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error adding app update: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif ($action === "update" && isset($_POST['update_id'])) {
        // Update existing record
        $update_id = $_POST['update_id'];
        try {
            $stmt = $pdo->prepare("UPDATE app_updates SET version=?, message=?, url=? WHERE id=?");
            $stmt->execute([$version, $message_text, $url, $update_id]);
            
            $message = "App update updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating app update: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Toggle active status
if (isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    try {
        $stmt = $pdo->prepare("UPDATE app_updates SET is_active = NOT is_active WHERE id=?");
        $stmt->execute([$id]);
        header("Location: app-update.php");
        exit();
    } catch (PDOException $e) {
        $message = "Error toggling status: " . $e->getMessage();
        $message_type = "error";
    }
}

// Delete update
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM app_updates WHERE id=?");
        $stmt->execute([$id]);
        header("Location: app-update.php");
        exit();
    } catch (PDOException $e) {
        $message = "Error deleting update: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch all updates
$updates = [];
try {
    $stmt = $pdo->query("SELECT * FROM app_updates ORDER BY release_date DESC");
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching updates: " . $e->getMessage();
    $message_type = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Update Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS remains the same as before */
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
        }
        
        header h1 {
            font-size: 2.5rem;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }
        
        header p {
            color: var(--text-light);
            font-size: 1.1rem;
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary);
            color: var(--white);
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-gray);
            font-weight: 500;
            font-size: 1rem;
        }
        
        .form-input {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 55, 242, 0.2);
        }
        
        textarea.form-input {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px 28px;
            font-weight: 600;
            font-size: 1rem;
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
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-success:hover {
            background: #2f855a;
        }
        
        .btn-warning {
            background: var(--warning);
        }
        
        .btn-warning:hover {
            background: #c05621;
        }
        
        .btn-danger {
            background: var(--danger);
        }
        
        .btn-danger:hover {
            background: #c53030;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        th {
            background-color: var(--light-gray);
            color: var(--dark-gray);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: rgba(108, 55, 242, 0.03);
        }
        
        .status-active {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            background: rgba(56, 161, 105, 0.1);
            color: var(--success);
            font-weight: 500;
        }
        
        .status-inactive {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            background: rgba(229, 62, 62, 0.1);
            color: var(--danger);
            font-weight: 500;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        footer {
            text-align: center;
            padding: 30px 0;
            color: var(--text-light);
            margin-top: 50px;
            border-top: 1px solid var(--medium-gray);
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-mobile-alt"></i> App Update Management</h1>
            <p>Manage application updates for your mobile users</p>
        </header>
        
        <?php if ($message): ?>
        <div class="message message-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus-circle"></i> Add New App Update
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Version Number*</label>
                            <input type="text" class="form-input" name="version" placeholder="e.g. 1.2.5" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Download URL*</label>
                            <input type="url" class="form-input" name="url" placeholder="https://example.com/app-update.apk" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Update Message*</label>
                        <textarea class="form-input" name="message" placeholder="Describe the update features and improvements..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Add Update
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Existing App Updates
            </div>
            <div class="card-body">
                <?php if (empty($updates)): ?>
                    <div style="text-align: center; padding: 30px; color: var(--text-light);">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px;"></i>
                        <h3>No app updates found</h3>
                        <p>Add your first app update using the form above</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Message</th>
                                <th>Release Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($updates as $update): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($update['version']); ?></strong></td>
                                <td><?php echo htmlspecialchars(substr($update['message'], 0, 60)); ?>...</td>
                                <td><?php echo date('M d, Y H:i', strtotime($update['release_date'])); ?></td>
                                <td>
                                    <span class="<?php echo $update['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $update['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="?toggle=<?php echo $update['id']; ?>" class="btn btn-sm <?php echo $update['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <i class="fas fa-power-off"></i> <?php echo $update['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <a href="#" onclick="editUpdate(<?php echo $update['id']; ?>, '<?php echo $update['version']; ?>', `<?php echo addslashes($update['message']); ?>`, '<?php echo $update['url']; ?>')" class="btn btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $update['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this update?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Hidden Edit Form -->
        <div class="card" id="editForm" style="display: none;">
            <div class="card-header">
                <i class="fas fa-edit"></i> Edit App Update
            </div>
            <div class="card-body">
                <form method="POST" id="editUpdateForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="update_id" id="update_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Version Number*</label>
                            <input type="text" class="form-input" name="version" id="edit_version" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Download URL*</label>
                            <input type="url" class="form-input" name="url" id="edit_url" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Update Message*</label>
                        <textarea class="form-input" name="message" id="edit_message" required></textarea>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn" onclick="document.getElementById('editForm').style.display='none';">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> App Update Management System | Version 1.0</p>
        </footer>
    </div>
    
    <script>
        function editUpdate(id, version, message, url) {
            document.getElementById('update_id').value = id;
            document.getElementById('edit_version').value = version;
            document.getElementById('edit_message').value = message;
            document.getElementById('edit_url').value = url;
            document.getElementById('editForm').style.display = 'block';
            
            // Scroll to the edit form
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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

$uid = $_POST['uid'] ?? null;

if (!$uid) {
    echo json_encode(['success' => false, 'message' => 'User ID is missing']);
    exit();
}

// Delete history
$delete_sql = "DELETE FROM history WHERE uid = ?";
$delete_stmt = prepareStatement($delete_sql);

$success = false;
$count = 0;

if ($delete_stmt instanceof PDOStatement) {
    $success = $delete_stmt->execute([$uid]);
} elseif ($delete_stmt instanceof mysqli_stmt) {
    $delete_stmt->bind_param("s", $uid);
    $success = $delete_stmt->execute();
}

if ($success) {
    // Get new count
    $count_sql = "SELECT COUNT(*) as count FROM history WHERE uid = ?";
    $count_stmt = prepareStatement($count_sql);
    
    if ($count_stmt instanceof PDOStatement) {
        $count_stmt->execute([$uid]);
        $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } elseif ($count_stmt instanceof mysqli_stmt) {
        $count_stmt->bind_param("s", $uid);
        $count_stmt->execute();
        $result = $count_stmt->get_result();
        $count = $result->fetch_assoc()['count'];
    }
    
    echo json_encode(['success' => true, 'count' => $count, 'message' => 'Transaction history cleared successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear transaction history']);
}
?>
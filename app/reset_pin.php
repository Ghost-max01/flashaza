<?php
session_start();
require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    if (isset($_POST['new_pin']) && !empty($_POST['new_pin']) && strlen($_POST['new_pin']) === 4 && is_numeric($_POST['new_pin'])) {
        $new_pin = $_POST['new_pin'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET pin_set = :pin WHERE uid = :uid");
            $stmt->execute(['pin' => $new_pin, 'uid' => $user_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'PIN updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made to PIN']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid PIN format']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
exit();
?>
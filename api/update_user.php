<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
include "config.php";

$uid = $_SESSION['user_id'];

// Read JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$field = $data['field'] ?? '';
$value = $data['value'] ?? null;

try {
    switch ($field) {
        case 'name':
            $name = trim((string)$value);
            if (strlen($name) < 2) throw new Exception('Name too short');
            $stmt = $pdo->prepare("UPDATE users SET name=? WHERE uid=?");
            $stmt->execute([$name, $uid]);
            echo json_encode(['ok'=>true]); 
            break;

        case 'number':
            $num = preg_replace('/\D/', '', (string)$value);
            if (!preg_match('/^\d{10}$/', $num)) throw new Exception('Invalid account number format');
            // must be unique across users
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE number=? AND uid<>?");
            $check->execute([$num, $uid]);
            if ($check->fetchColumn() > 0) throw new Exception('Account number already in use');
            $stmt = $pdo->prepare("UPDATE users SET number=? WHERE uid=?");
            $stmt->execute([$num, $uid]);
            echo json_encode(['ok'=>true]); 
            break;

        case 'email_alert':
            // 0 or 1
            $val = (int)$value === 1 ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE users SET email_alert=? WHERE uid=?");
            $stmt->execute([$val, $uid]);
            echo json_encode(['ok'=>true]);
            break;

        default:
            throw new Exception('Unknown field');
    }
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
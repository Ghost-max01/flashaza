<?php
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(["message" => "Not logged in"]); exit; }

require_once "config.php";
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';
$response = ["message" => "Not available"];

if ($action === "reset-history") {
    $pdo->prepare("DELETE FROM history WHERE uid = :uid")->execute(['uid' => $user_id]);
    $pdo->prepare("UPDATE users SET amount_in = 0.00, amount_out = 0.00 WHERE uid = :uid")->execute(['uid' => $user_id]);
    $response = ["message" => "Transaction history reset successfully"];
}

elseif ($action === "join-channel") {
    $stmt = $pdo->query("SELECT price FROM price WHERE type='channel' LIMIT 1");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response = ["url" => "https://t.me/web404space"];
    }
}

elseif ($action === "upgrade-account") {
    $stmt = $pdo->prepare("SELECT plan FROM users WHERE uid = :uid LIMIT 1");
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && strtolower($user['plan']) !== "lifetime") {
        $response = ["redirect" => "plan.php"];
    } else {
        $response = ["message" => "You are already a lifetime user"];
    }
}

elseif ($action === "logout") {
    session_destroy();
    $response = ["message" => "Logged out"];
}

header("Content-Type: application/json");
echo json_encode($response);
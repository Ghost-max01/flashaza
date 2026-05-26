<?php
session_start();
if(!isset($_SESSION['user_id'])){
    echo json_encode(["ok"=>false, "error"=>"Unauthorized"]);
    exit;
}
include "config.php"; // $pdo connect

$uid = $_SESSION['user_id'];

// Fetch balance
$stmt = $pdo->prepare("SELECT balance FROM users WHERE uid=?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user){
    echo json_encode(["ok"=>false, "error"=>"User not found"]);
    exit;
}

$balance = (float)$user['balance'];
$amount  = $balance / 4;

// ---------- Format Dates ----------
date_default_timezone_set("Africa/Lagos");

// date3 → MMM dd, yyyy HH:mm
$date3 = date("M d, Y H:i");

// date1 → MMM d{suffix}, HH:mm:ss
$day = (int)date("j");
$suffix = "th";
if($day % 10 == 1 && $day != 11) $suffix = "st";
elseif($day % 10 == 2 && $day != 12) $suffix = "nd";
elseif($day % 10 == 3 && $day != 13) $suffix = "rd";
$date1 = date("M j", time()) . $suffix . date(", H:i:s");

// ---------- Insert ----------
$stmt = $pdo->prepare("INSERT INTO history (uid, amount, category, balance, date1, date3) 
                       VALUES (?,?,?,?,?,?)");
$ok = $stmt->execute([$uid, $amount, "deposit", $balance, $date1, $date3]);

if($ok){
    echo json_encode(["ok"=>true]);
} else {
    echo json_encode(["ok"=>false, "error"=>"Insert failed"]);
}
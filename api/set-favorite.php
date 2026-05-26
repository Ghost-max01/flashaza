<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$user_id       = $_SESSION['user_id'];
$accountNumber = $_SESSION['accountnumber'] ?? null;
$bankName      = $_SESSION['bankname'] ?? null;
$accountName   = $_SESSION['accountname'] ?? null;

if ($accountNumber && $bankName && $accountName) {
    $stmt = $pdo->prepare("UPDATE beneficiary 
        SET favorite = 1 
        WHERE uid=? AND accountnumber=? AND bankname=? AND accountname=?");
    $stmt->execute([$user_id, $accountNumber, $bankName, $accountName]);
}

header("Location: transaction-success.php");
exit;
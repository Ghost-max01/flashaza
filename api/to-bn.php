<?php
// to-bn.php
session_start();

// Collect POST
$name = $_POST['name'] ?? '';
$url  = $_POST['url'] ?? '';
$code = $_POST['code'] ?? '';

// Store in session (safe way)
$_SESSION['bank'] = [
    "name" => $name,
    "url"  => $url,
    "code" => $code
];

// Redirect or display
header("Location: to-bnk.php"); 
exit;
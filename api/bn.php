<?php
// bank.php
header("Content-Type: application/json");

// Fetch bank list from API
$url = "https://webtech.net.ng/opy/list/retrieve.php";
$postData = [
    "username" => "WEB_LORD198",
    "password" => "Said0051$"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
$response = curl_exec($ch);
curl_close($ch);

// Just echo raw response back to frontend JS
echo $response;
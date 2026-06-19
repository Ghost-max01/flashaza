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

// Ensure we return JSON. If remote returns valid JSON, pass it through.
// Otherwise return an empty array payload to avoid breaking client JSON parsing.
$trim = trim((string)$response);
if ($trim === '') {
    echo json_encode([]);
    exit;
}
// Try decode; if ok, echo canonical JSON
$decoded = json_decode($response, true);
if (is_array($decoded)) {
    echo json_encode($decoded);
    exit;
}
// Not valid JSON — return empty array
echo json_encode([]);
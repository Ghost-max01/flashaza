<?php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=UTF-8');

$secret = trim(getenv('PAYSTACK_SECRET') ?: '');
if ($secret === '') {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'PAYSTACK_SECRET not configured']);
    exit;
}

$ch = curl_init('https://api.paystack.co/bank');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['status' => false, 'message' => 'Paystack request failed: ' . $curlErr]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode);
    echo json_encode(['status' => false, 'message' => 'Paystack HTTP error: ' . $httpCode, 'raw' => $response]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
    http_response_code(502);
    echo json_encode(['status' => false, 'message' => 'Invalid Paystack response', 'raw' => $response]);
    exit;
}

$banks = $data['data'];
usort($banks, function($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

echo json_encode(['status' => true, 'data' => $banks]);

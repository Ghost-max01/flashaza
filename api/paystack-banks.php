<?php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=UTF-8');

$secret = trim(getenv('PAYSTACK_SECRET_KEY') ?: getenv('PAYSTACK_SECRET') ?: '');
if ($secret === '') {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'PAYSTACK_SECRET_KEY not configured']);
    exit;
}

$cacheFile = __DIR__ . '/banks_cache.json';
$cacheTtl = 86400; // 24 hours

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cached = file_get_contents($cacheFile);
    if ($cached !== false) {
        $payload = json_decode($cached, true);
        if (is_array($payload) && isset($payload['status']) && isset($payload['data'])) {
            echo json_encode($payload);
            exit;
        }
    }
}

$url = 'https://api.paystack.co/bank?country=nigeria';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/json',
        'Cache-Control: no-cache'
    ],
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    if (file_exists($cacheFile)) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false) {
            echo $cached;
            exit;
        }
    }
    http_response_code(502);
    echo json_encode(['status' => false, 'message' => 'Paystack request failed: ' . $curlErr]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    if (file_exists($cacheFile)) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false) {
            echo $cached;
            exit;
        }
    }
    http_response_code($httpCode);
    echo json_encode(['status' => false, 'message' => 'Paystack HTTP error: ' . $httpCode, 'raw' => $response]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
    if (file_exists($cacheFile)) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false) {
            echo $cached;
            exit;
        }
    }
    http_response_code(502);
    echo json_encode(['status' => false, 'message' => 'Invalid Paystack response', 'raw' => $response]);
    exit;
}

$banks = [];
foreach ($data['data'] as $bank) {
    $banks[] = [
        'name' => $bank['name'] ?? '',
        'code' => $bank['code'] ?? '',
        'slug' => trim((string)($bank['slug'] ?? '')),
        'active' => $bank['active'] ?? false,
        'pay_with_bank' => $bank['pay_with_bank'] ?? false,
    ];
}

usort($banks, function($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$payload = ['status' => true, 'data' => $banks];
@file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES));

echo json_encode($payload);


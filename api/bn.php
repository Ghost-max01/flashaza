<?php
// bn.php
header('Content-Type: application/json');

function getEnvValue($name) {
    $value = getenv($name);
    if ($value !== false && trim($value) !== '') {
        return trim($value);
    }
    if (!empty($_ENV[$name])) {
        return trim($_ENV[$name]);
    }
    if (!empty($_SERVER[$name])) {
        return trim($_SERVER[$name]);
    }
    return '';
}

$paystackKey = getEnvValue('PAYSTACK_SECRET_KEY');
if ($paystackKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'PAYSTACK_SECRET_KEY not configured']);
    exit();
}

$ch = curl_init('https://api.paystack.co/bank?currency=NGN');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $paystackKey,
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl error: ' . $err]);
    exit();
}

$data = json_decode($response, true);
if ($code !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Paystack API returned HTTP ' . $code, 'raw' => $data]);
    exit();
}
if (!is_array($data) || !isset($data['status']) || $data['status'] !== true || !isset($data['data']) || !is_array($data['data'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid Paystack response', 'raw' => $data]);
    exit();
}

$icons = [
    'access' => '../images/toban/access.png',
    'first' => '../images/toban/first.png',
    'gtbank' => '../images/toban/gt.png',
    'zenithbank' => '../images/toban/zenith.png',
    'uba' => '../images/toban/uba.png',
    'opay' => '../images/toban/opay.png',
];

$banks = [];
foreach ($data['data'] as $bank) {
    if (!isset($bank['name'], $bank['code'])) {
        continue;
    }
    $slug = strtolower(trim($bank['slug'] ?? ''));
    $icon = $icons[$slug] ?? '../images/toban/coin.png';
    $banks[] = [
        'name' => $bank['name'],
        'url' => $icon,
        'code' => $bank['code']
    ];
}

if (empty($banks)) {
    http_response_code(500);
    echo json_encode(['error' => 'No banks returned from Paystack']);
    exit();
}

echo json_encode($banks);

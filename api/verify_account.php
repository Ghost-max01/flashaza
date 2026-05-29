<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);
if (ob_get_level() > 0) {
    ob_clean();
}
if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'error: invalid method';
    exit;
}

$account_number = $_POST['account_number'] ?? '';
$bank_code = $_POST['bank_code'] ?? '';

if (!preg_match('/^\d{10}$/', $account_number) || $bank_code === '') {
    http_response_code(400);
    echo 'error: invalid input';
    exit;
}

$secretKey = trim(getenv('PAYSTACK_SECRET_KEY') ?: '');
$defaultBank = trim(getenv('PAYSTACK_BANK_CODE') ?: '');
if ($defaultBank !== '' && $bank_code === '999992') {
    $bank_code = $defaultBank;
}

if ($secretKey !== '') {
    $url = sprintf(
        'https://api.paystack.co/bank/resolve?account_number=%s&bank_code=%s',
        urlencode($account_number),
        urlencode($bank_code)
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $secretKey,
            'Cache-Control: no-cache',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo 'error: network error';
        exit;
    }

    $data = json_decode($res, true);
    if (!is_array($data)) {
        echo 'error: invalid Paystack response';
        exit;
    }

    if (!empty($data['status']) && $data['status'] === true && !empty($data['data']['account_name'])) {
        echo $data['data']['account_name'];
        exit;
    }

    $message = $data['message'] ?? 'Unable to resolve account number';
    echo 'error: ' . trim($message);
    exit;
}

$remote = "https://webtech.net.ng/vrf/verify.php";
$ch = curl_init($remote);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'account_number' => $account_number,
        'bank_code'      => $bank_code,
    ]),
    CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
if ($res === false) {
    echo 'error: request failed';
} else {
    $cleaned = trim(explode("\n", $res)[0]);
    $cleaned = preg_replace('/^(account\s*name|name|acct)[\s:;\-]+/i', '', $cleaned);
    $cleaned = preg_replace('/\s*[-–—].*$/', '', $cleaned);
    $cleaned = trim($cleaned);
    if (strlen($cleaned) >= 3 && !preg_match('/error|invalid|not\s*found/i', $cleaned)) {
        echo $cleaned;
    } else {
        echo 'error: unable to resolve account';
    }
}
curl_close($ch);

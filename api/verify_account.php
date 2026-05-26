<?php
// verify_account.php
header('Content-Type: text/plain; charset=utf-8');

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
    echo $res; // either an account name or an error text
}
curl_close($ch);
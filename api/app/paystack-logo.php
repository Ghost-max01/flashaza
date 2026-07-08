<?php
if (session_status()===PHP_SESSION_NONE) session_start();

$code = trim((string)($_GET['code'] ?? ''));
$slug = trim((string)($_GET['slug'] ?? ''));
$name = trim((string)($_GET['name'] ?? ''));

if ($code === '' && $slug === '' && $name !== '') {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
    $slug = trim($slug, '-');
}

$target = $code !== '' ? $code : $slug;
if ($target === '') {
    http_response_code(404);
    exit;
}

$url = 'https://paystack.com/banks/' . rawurlencode($target) . '.png';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
        'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        'Referer: https://paystack.com/'
    ],
]);

$image = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($err || $httpCode < 200 || $httpCode >= 300 || stripos((string)$contentType, 'image/') === false || $image === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400, s-maxage=86400');
echo $image;

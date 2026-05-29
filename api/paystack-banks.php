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

// ── Enrich with logos (best-effort, never blocks the response) ──

// Source 1: supermx1 GitHub dataset – matched by bank CODE (high coverage)
$logoByCode = [];
$logoBySlug = [];
$logoCh = curl_init('https://supermx1.github.io/nigerian-banks-api/data.json');
curl_setopt_array($logoCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$logoResp = curl_exec($logoCh);
$logoHttpCode = curl_getinfo($logoCh, CURLINFO_HTTP_CODE);
curl_close($logoCh);

if ($logoResp && $logoHttpCode >= 200 && $logoHttpCode < 300) {
    $logoData = json_decode($logoResp, true);
    if (is_array($logoData)) {
        $logoBase = 'https://supermx1.github.io/nigerian-banks-api/';
        foreach ($logoData as $lb) {
            $lCode = trim((string)($lb['code'] ?? ''));
            $lSlug = trim((string)($lb['slug'] ?? ''));
            $lLogo = trim((string)($lb['logo'] ?? ''));
            if ($lLogo && !str_starts_with($lLogo, 'http')) {
                $lLogo = $logoBase . $lLogo;
            }
            if ($lCode && $lLogo) $logoByCode[$lCode] = $lLogo;
            if ($lSlug && $lLogo) $logoBySlug[$lSlug] = $lLogo;
        }
    }
}

// Source 2: nigerianbanks.xyz – matched by normalized NAME (fallback)
$logoByName = [];
$ngCh = curl_init('https://nigerianbanks.xyz/');
curl_setopt_array($ngCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$ngResp = curl_exec($ngCh);
$ngHttpCode = curl_getinfo($ngCh, CURLINFO_HTTP_CODE);
curl_close($ngCh);

if ($ngResp && $ngHttpCode >= 200 && $ngHttpCode < 300) {
    $ngData = json_decode($ngResp, true);
    if (is_array($ngData)) {
        foreach ($ngData as $nb) {
            $nName = strtolower(trim((string)($nb['name'] ?? $nb['bank_name'] ?? '')));
            $nLogo = $nb['logo'] ?? $nb['url'] ?? $nb['image'] ?? '';
            if ($nName && $nLogo && str_starts_with((string)$nLogo, 'http')) {
                $logoByName[$nName] = (string)$nLogo;
            }
        }
    }
}

// Merge logos into banks: code → slug → name (priority order)
foreach ($banks as &$b) {
    $code = (string)($b['code'] ?? '');
    $slug = (string)($b['slug'] ?? '');
    $name = strtolower(trim((string)($b['name'] ?? '')));

    $logo = '';
    if ($code && isset($logoByCode[$code])) {
        $logo = $logoByCode[$code];
    } elseif ($slug && isset($logoBySlug[$slug])) {
        $logo = $logoBySlug[$slug];
    } elseif ($name && isset($logoByName[$name])) {
        $logo = $logoByName[$name];
    }
    $b['logo'] = $logo;
}
unset($b);

usort($banks, function($a, $b) {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$payload = ['status' => true, 'data' => $banks];
@file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES));

echo json_encode($payload);


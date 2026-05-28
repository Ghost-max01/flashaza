<?php
// config.php - Supabase/Postgres only
// Requires DATABASE_URL environment variable

$pdo = null;
$conn = null;
$DB_DRIVER = null;
$SUPABASE_AVAILABLE = false;
$SUPABASE_ERROR = '';

function db_fail($msg) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => $msg]);
    // Do not exit, allow fallback handling
}

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    $SUPABASE_ERROR = 'DATABASE_URL not set. Supabase connection required.';
} else {
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        $SUPABASE_ERROR = 'Invalid DATABASE_URL';
    } else {
        $db_user = $parts['user'] ?? '';
        $db_pass = $parts['pass'] ?? '';
        $db_host = $parts['host'] ?? 'localhost';
        $db_port = $parts['port'] ?? 5432;
        $db_name = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $db_host, $db_port, $db_name);
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $conn = $pdo;
            $DB_DRIVER = 'pgsql';
            $SUPABASE_AVAILABLE = true;
        } catch (Exception $e) {
            $SUPABASE_AVAILABLE = false;
            $SUPABASE_ERROR = 'Database connection failed: ' . $e->getMessage();
            error_log('Supabase connection failed: ' . $e->getMessage());
        }
    }
}

// --- Supabase REST helper (use when you prefer HTTPS REST calls instead of direct TCP) ---
$USE_SUPABASE_REST = false;
$SUPABASE_URL = trim(getenv('SUPABASE_URL') ?: '');
$SUPABASE_ANON_KEY = trim(getenv('SUPABASE_ANON_KEY') ?: '');
$SUPABASE_SERVICE_ROLE_KEY = trim(getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
if ($SUPABASE_URL !== '' && $SUPABASE_SERVICE_ROLE_KEY !== '') {
    $USE_SUPABASE_REST = true;
    if ($SUPABASE_ANON_KEY === '') {
        $SUPABASE_ANON_KEY = $SUPABASE_SERVICE_ROLE_KEY;
    }
}

function supabase_request($method, $path, $body = null, $extraHeaders = []) {
    global $SUPABASE_URL, $SUPABASE_ANON_KEY, $SUPABASE_SERVICE_ROLE_KEY;
    $base = rtrim($SUPABASE_URL, '/') . '/rest/v1';
    $url = $base . $path;

    $ch = curl_init($url);
    $headers = [
        'apikey: ' . $SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $SUPABASE_SERVICE_ROLE_KEY,
    ];
    foreach ($extraHeaders as $h) {
        $headers[] = $h;
    }

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body;
    } elseif ($method === 'PATCH' || $method === 'PUT' || $method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => $err, 'status' => 0, 'raw' => null];
    return ['ok' => true, 'status' => $code, 'raw' => $res];
}

function supabase_user_exists_by_email($email) {
    global $USE_SUPABASE_REST;
    if (!$USE_SUPABASE_REST) return false;
    $path = '/users?select=1&email=eq.' . rawurlencode($email);
    $r = supabase_request('GET', $path);
    if (!$r['ok'] || $r['status'] !== 200) return false;
    $data = json_decode($r['raw'], true);
    return is_array($data) && count($data) > 0;
}

function supabase_user_exists_by_number($number) {
    global $USE_SUPABASE_REST;
    if (!$USE_SUPABASE_REST) return false;
    $path = '/users?select=1&number=eq.' . rawurlencode($number);
    $r = supabase_request('GET', $path);
    if (!$r['ok'] || $r['status'] !== 200) return false;
    $data = json_decode($r['raw'], true);
    return is_array($data) && count($data) > 0;
}

function supabase_insert_user(array $row) {
    global $USE_SUPABASE_REST;
    if (!$USE_SUPABASE_REST) return ['ok' => false, 'message' => 'Supabase REST not configured'];
    $path = '/users';
    $body = json_encode($row);
    $headers = ['Content-Type: application/json', 'Prefer: return=representation'];
    $r = supabase_request('POST', $path, $body, $headers);
    if (!$r['ok']) return ['ok' => false, 'message' => $r['error'] ?? 'request failed'];
    if ($r['status'] !== 201 && $r['status'] !== 200) {
        return ['ok' => false, 'message' => 'Insert failed: HTTP ' . $r['status']];
    }
    $data = json_decode($r['raw'], true);
    return ['ok' => true, 'data' => $data];
}
?>
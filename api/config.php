<?php
// config.php - Supabase/Postgres only
// Requires DATABASE_URL environment variable

$pdo = null;
$conn = null;
$DB_DRIVER = null;
$SUPABASE_AVAILABLE = false;
$SUPABASE_ERROR = '';

$SUPABASE_URL = trim(getenv('SUPABASE_URL') ?: '');
$SUPABASE_ANON_KEY = trim(getenv('SUPABASE_ANON_KEY') ?: '');
$SUPABASE_SERVICE_ROLE_KEY = trim(getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
$USE_SUPABASE_REST = false;
if ($SUPABASE_URL !== '' && $SUPABASE_SERVICE_ROLE_KEY !== '') {
    $USE_SUPABASE_REST = true;
    if ($SUPABASE_ANON_KEY === '') {
        $SUPABASE_ANON_KEY = $SUPABASE_SERVICE_ROLE_KEY;
    }
}

class SupabasePDOStatement {
    private $pdo;
    private $sql;
    private $params = [];
    private $result = null;
    private $rowCount = 0;
    private $rowPointer = 0;

    public function __construct($pdo, $sql) {
        $this->pdo = $pdo;
        $this->sql = $sql;
    }

    public function execute($params = null) {
        if ($params !== null) {
            $this->params = $params;
        }
        $request = $this->pdo->buildRequest($this->sql, $this->params);
        if (!$request) {
            throw new Exception('Unsupported SQL for Supabase REST: ' . $this->sql);
        }
        $response = $this->pdo->request($request['method'], $request['path'], $request['body'], $request['headers'] ?? []);
        if (!$response['ok']) {
            throw new Exception('Supabase REST request failed: ' . ($response['error'] ?? 'unknown'));
        }
        $this->rowCount = $response['rowCount'] ?? 0;
        $this->result = json_decode($response['raw'] ?? '[]', true);
        if ($this->result === null && in_array($request['method'], ['PATCH', 'POST', 'DELETE'], true)) {
            $this->result = [];
        }
        $this->rowPointer = 0;
        return true;
    }

    public function fetch($mode = null) {
        if ($this->result === null) {
            $this->execute();
        }
        if (!is_array($this->result) || $this->rowPointer >= count($this->result)) {
            return false;
        }
        return $this->result[$this->rowPointer++];
    }

    public function fetchAll($mode = null) {
        if ($this->result === null) {
            $this->execute();
        }
        return is_array($this->result) ? $this->result : [];
    }

    public function fetchColumn($col = 0) {
        $row = $this->fetch();
        if (!$row) {
            return false;
        }
        if (is_int($col)) {
            return array_values($row)[$col] ?? false;
        }
        return $row[$col] ?? false;
    }

    public function rowCount() {
        return $this->rowCount;
    }

    public function setFetchMode() {
        return true;
    }
}

class SupabasePDO {
    private $url;
    private $anonKey;
    private $serviceKey;
    public $lastInsertId = 0;

    public function __construct($url, $anonKey, $serviceKey) {
        $this->url = rtrim($url, '/');
        $this->anonKey = $anonKey;
        $this->serviceKey = $serviceKey;
    }

    public function prepare($sql) {
        return new SupabasePDOStatement($this, $sql);
    }

    public function query($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function beginTransaction() {
        return true;
    }

    public function commit() {
        return true;
    }

    public function rollBack() {
        return true;
    }

    public function inTransaction() {
        return false;
    }

    public function lastInsertId($name = null) {
        return $this->lastInsertId;
    }

    public function request($method, $path, $body = null, $headers = []) {
        $url = $this->url . '/rest/v1' . $path;
        $ch = curl_init($url);
        $defaultHeaders = [
            'apikey: ' . $this->anonKey,
            'Authorization: Bearer ' . $this->serviceKey,
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        } elseif (in_array($method, ['PATCH', 'PUT', 'DELETE'], true)) {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            if (!headers_sent()) header('X-Flashaza-Debug: Supabase request error: ' . substr($err, 0, 120));
            return ['ok' => false, 'error' => $err, 'status' => 0, 'raw' => null];
        }
        if ($status < 200 || $status >= 300) {
            if (!headers_sent()) header('X-Flashaza-Debug: Supabase HTTP ' . $status . ' on ' . $method . ' ' . $path);
            return ['ok' => false, 'error' => 'HTTP ' . $status, 'status' => $status, 'raw' => $raw];
        }
        return ['ok' => true, 'status' => $status, 'raw' => $raw, 'rowCount' => null];
    }

    public function buildRequest($sql, $params) {
        $sql = trim($sql);
        $type = strtoupper(strtok($sql, " \t\n\r"));
        if ($type === 'SELECT') {
            return $this->buildSelectRequest($sql, $params);
        }
        if ($type === 'UPDATE') {
            return $this->buildUpdateRequest($sql, $params);
        }
        if ($type === 'INSERT') {
            return $this->buildInsertRequest($sql, $params);
        }
        if ($type === 'DELETE') {
            return $this->buildDeleteRequest($sql, $params);
        }
        return null;
    }

    private function buildSelectRequest($sql, $params) {
        if (!preg_match('/^SELECT\s+(.*?)\s+FROM\s+(\w+)(?:\s+WHERE\s+(.*?))?(?:\s+ORDER\s+BY\s+(.*?))?(?:\s+LIMIT\s+(\d+))?$/is', $sql, $m)) {
            return null;
        }
        $select = trim($m[1]);
        $table = trim($m[2]);
        $where = trim($m[3] ?? '');
        $order = trim($m[4] ?? '');
        $limit = trim($m[5] ?? '');
        if ($select === '*') {
            $query = 'select=*';
        } else {
            $query = 'select=' . str_replace(' ', '', $select);
        }
        $filters = $this->buildFilters($where, $params);
        if ($filters !== '') {
            $query .= '&' . $filters;
        }
        if ($order !== '') {
            $orders = array_map('trim', explode(',', $order));
            $normalized = [];
            foreach ($orders as $orderPart) {
                if (preg_match('/^(\w+)(?:\s+(ASC|DESC))?$/i', $orderPart, $om)) {
                    $field = $om[1];
                    $direction = isset($om[2]) && strtoupper($om[2]) === 'DESC' ? 'desc' : (isset($om[2]) && strtoupper($om[2]) === 'ASC' ? 'asc' : 'asc');
                    $normalized[] = $field . '.' . $direction;
                } else {
                    $normalized[] = str_replace(' ', '.', $orderPart);
                }
            }
            $query .= '&order=' . rawurlencode(implode(',', $normalized));
        }
        if ($limit !== '') {
            $query .= '&limit=' . intval($limit);
        }
        return ['method' => 'GET', 'path' => '/' . $table . '?' . $query, 'body' => null, 'headers' => []];
    }

    private function buildUpdateRequest($sql, $params) {
        if (!preg_match('/^UPDATE\s+(\w+)\s+SET\s+(.*?)\s+WHERE\s+(.*)$/is', $sql, $m)) {
            return null;
        }
        $table = trim($m[1]);
        $assignments = trim($m[2]);
        $where = trim($m[3]);
        $body = [];
        $valueParams = is_array($params) ? array_values($params) : [];
        foreach (preg_split('/\s*,\s*/', $assignments) as $assignment) {
            $assignment = trim($assignment);
            if (preg_match('/^(\w+)\s*=\s*([:?]?\w+)$/', $assignment, $a)) {
                $key = $a[1];
                $param = $a[2];
                if ($param === '?') {
                    $body[$key] = array_shift($valueParams);
                } else {
                    $body[$key] = $params[ltrim($param, ':')] ?? null;
                }
            } elseif (preg_match('/^(\w+)\s*=\s*(?:\'([^\']*)\'|"([^"]*)"|(\d+))$/', $assignment, $a)) {
                $key = $a[1];
                $body[$key] = $a[2] !== '' ? $a[2] : ($a[3] !== '' ? $a[3] : $a[4]);
            }
        }
        $filters = $this->buildFilters($where, $params);
        return ['method' => 'PATCH', 'path' => '/' . $table . '?' . $filters, 'body' => json_encode($body), 'headers' => ['Content-Type: application/json', 'Prefer: return=representation']];
    }

    private function buildInsertRequest($sql, $params) {
        if (!preg_match('/^INSERT\s+INTO\s+(\w+)\s*\((.*?)\)\s*VALUES\s*\((.*?)\)$/is', $sql, $m)) {
            return null;
        }
        $table = trim($m[1]);
        $columns = array_map('trim', explode(',', $m[2]));
        $values = array_map('trim', explode(',', $m[3]));
        $body = [];
        $valueParams = is_array($params) ? array_values($params) : [];
        foreach ($columns as $i => $col) {
            $val = $values[$i] ?? '';
            if ($val === '?') {
                $body[$col] = array_shift($valueParams);
            } elseif (substr($val, 0, 1) === ':') {
                $body[$col] = $params[ltrim($val, ':')] ?? null;
            } else {
                $body[$col] = trim($val, "'\"");
            }
        }
        return ['method' => 'POST', 'path' => '/' . $table, 'body' => json_encode($body), 'headers' => ['Content-Type: application/json', 'Prefer: return=representation']];
    }

    private function buildDeleteRequest($sql, $params) {
        if (!preg_match('/^DELETE\s+FROM\s+(\w+)\s+WHERE\s+(.*)$/is', $sql, $m)) {
            return null;
        }
        $table = trim($m[1]);
        $where = trim($m[2]);
        $filters = $this->buildFilters($where, $params);
        return ['method' => 'DELETE', 'path' => '/' . $table . '?' . $filters, 'body' => null, 'headers' => ['Prefer: return=representation']];
    }

    private function buildFilters($where, $params) {
        if ($where === '') {
            return '';
        }
        $parts = preg_split('/\s+AND\s+/i', $where);
        $filters = [];
        $valueParams = is_array($params) ? array_values($params) : [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(\w+)\s*=\s*([:?]?\w+)$/', $part, $m)) {
                $key = $m[1];
                $param = $m[2];
                if ($param === '?') {
                    $value = array_shift($valueParams);
                } else {
                    $value = $params[ltrim($param, ':')] ?? null;
                }
                $filters[] = $key . '=eq.' . rawurlencode((string)$value);
                continue;
            }
            if (preg_match('/^(\w+)\s*=\s*(?:\'([^\']*)\'|"([^"]*)"|(\d+))$/', $part, $m)) {
                $key = $m[1];
                $value = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
                $filters[] = $key . '=eq.' . rawurlencode((string)$value);
            }
        }
        return implode('&', $filters);
    }
}

function db_fail($msg) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => $msg]);
    // Do not exit, allow fallback handling
}

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl && !$USE_SUPABASE_REST) {
    $SUPABASE_ERROR = 'DATABASE_URL not set. Supabase connection required.';
} elseif (!$USE_SUPABASE_REST) {
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
} elseif ($USE_SUPABASE_REST) {
    $pdo = new SupabasePDO($SUPABASE_URL, $SUPABASE_ANON_KEY, $SUPABASE_SERVICE_ROLE_KEY);
    $conn = $pdo;
    $DB_DRIVER = 'supabase_rest';
    $SUPABASE_AVAILABLE = true;
}

// --- Supabase REST helper (use when you prefer HTTPS REST calls instead of direct TCP) ---
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

    if ($err) {
        if (!headers_sent()) header('X-Flashaza-Debug: Supabase request error: ' . substr($err, 0, 120));
        return ['ok' => false, 'error' => $err, 'status' => 0, 'raw' => null];
    }
    if ($code < 200 || $code >= 300) {
        if (!headers_sent()) header('X-Flashaza-Debug: Supabase HTTP ' . $code . ' on ' . $method . ' ' . $path);
        return ['ok' => false, 'error' => 'HTTP ' . $code, 'status' => $code, 'raw' => $res];
    }
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
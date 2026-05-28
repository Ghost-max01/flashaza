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
?>
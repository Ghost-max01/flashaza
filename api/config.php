<?php
// config.php - environment-aware DB connection
// Supports:
// - Supabase/Postgres via `DATABASE_URL` (recommended for Vercel + Supabase)
// - MySQL via DB_HOST/DB_NAME/DB_USER/DB_PASS env vars (legacy)

// Initialize
$pdo = null;
$conn = null;
$DB_DRIVER = null;

// Helper: cleanly fail
function db_fail($msg) {
    // In web context return JSON (most endpoints expect HTML but JSON is safer for errors)
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(["status" => false, "message" => $msg]);
    exit;
}

// 1) If DATABASE_URL is provided, prefer it (Supabase/Postgres)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('DATABASE_URL'.'');
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    if ($parts === false) db_fail('Invalid DATABASE_URL');

    $db_user = $parts['user'] ?? '';
    $db_pass = $parts['pass'] ?? '';
    $db_host = $parts['host'] ?? 'localhost';
    $db_port = $parts['port'] ?? 5432;
    // path begins with /dbname
    $db_name = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

    try {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $db_host, $db_port, $db_name);
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $conn = $pdo;
        $DB_DRIVER = 'pgsql';
    } catch (Exception $e) {
        db_fail('Database (Postgres) connection failed: ' . $e->getMessage());
    }
} else {
    // 2) Fall back to MySQL using env vars (useful locally or on non-Supabase setups)
    $DB_HOST = getenv('DB_HOST') ?: 'localhost';
    $DB_NAME = getenv('DB_NAME') ?: 'atjnuaqu_opay';
    $DB_USER = getenv('DB_USER') ?: 'atjnuaqu_opay';
    $DB_PASS = getenv('DB_PASS') ?: '';
    $DB_CHARSET = getenv('DB_CHARSET') ?: 'utf8mb4';

    try {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $conn = $pdo;
        $DB_DRIVER = 'mysql';
    } catch (Exception $e) {
        // Try mysqli as a last-resort for MySQL
        if (function_exists('mysqli_connect')) {
            $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
            if ($mysqli->connect_errno) {
                db_fail('Database connection failed: ' . $mysqli->connect_error);
            }
            $mysqli->set_charset($DB_CHARSET);
            $conn = $mysqli;
            $DB_DRIVER = 'mysqli';
        } else {
            db_fail('Database connection failed: ' . $e->getMessage());
        }
    }
}

// Export $pdo and $conn for legacy code
// $pdo is a PDO instance when available; $conn is either PDO or mysqli
?>
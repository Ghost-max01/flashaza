<?php
// config.php - environment-aware DB connection for Admin
if (getenv('DATABASE_URL') || getenv('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
} else {
    // Database credentials
    $host = 'localhost';
    $db   = 'atjnuaqu_opay';
    $user = 'atjnuaqu_opay';
    $pass = 'Maxwell198$';
    $charset = 'utf8mb4';

    // Try PDO connection first
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn = $pdo;
    } catch (Exception $e) {
        // If PDO fails, fallback to MySQLi
        $conn = new mysqli($host, $user, $pass, $db);

        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }
    }
}
?>
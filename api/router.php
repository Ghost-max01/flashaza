<?php
// router.php - Single entry-point router/front-controller for Vercel

// Get request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = ltrim($parsedUrl['path'] ?? '', '/');

// Security: Prevent directory traversal
if (strpos($path, '..') !== false) {
    http_response_code(400);
    echo "Invalid path";
    exit;
}

// Map root URL / to index.php
if ($path === '' || $path === 'index.php') {
    $path = 'index.php';
}

$baseDir = realpath(__DIR__ . '/app');
if ($baseDir === false) {
    http_response_code(500);
    echo "Server misconfiguration";
    exit;
}

$searchDirs = [
    $baseDir,
    realpath(__DIR__),
    realpath(__DIR__ . '/..'),
];
$searchDirs = array_filter($searchDirs, fn($dir) => $dir !== false);

$targetFile = null;
$baseDir = null;
foreach ($searchDirs as $dir) {
    $candidate = $dir . '/' . $path;
    if (is_dir($candidate)) {
        $candidate = rtrim($candidate, '/') . '/index.php';
    }
    $realCandidate = realpath($candidate);
    if ($realCandidate !== false && strpos($realCandidate, $dir) === 0) {
        $targetFile = $realCandidate;
        $baseDir = $dir;
        break;
    }
}

if ($targetFile === null) {
    http_response_code(404);
    echo "Page Not Found";
    exit;
}

// Change working directory to the base app directory so relative includes resolve correctly
chdir($baseDir);

// If file exists, execute it
if (file_exists($targetFile) && is_file($targetFile)) {
    // Prevent direct execution of database credentials/config files
    if (basename($targetFile) === 'config.php') {
        http_response_code(403);
        echo "Access Denied";
        exit;
    }

    // Set PHP server environment variables to emulate natural URL routing
    $_SERVER['PHP_SELF'] = '/' . $path;
    $_SERVER['SCRIPT_NAME'] = '/' . $path;

    // Load file
    require $targetFile;
    exit;
}

// Try appending .php extension if omitted (e.g. /dashboard -> /api/dashboard.php)
$targetFileWithPhp = $targetFile . '.php';
if (file_exists($targetFileWithPhp) && is_file($targetFileWithPhp)) {
    $_SERVER['PHP_SELF'] = '/' . $path . '.php';
    $_SERVER['SCRIPT_NAME'] = '/' . $path . '.php';
    require $targetFileWithPhp;
    exit;
}

// Fallback: 404 Page Not Found
http_response_code(404);
echo "Page Not Found";
exit;

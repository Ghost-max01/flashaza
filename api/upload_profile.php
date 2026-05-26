<?php
// Upload image to /profile and update users.profile with absolute URL
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
include "config.php";

$uid = $_SESSION['user_id'];

if (!isset($_FILES['profile']) || $_FILES['profile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'error'=>'No file or upload error']); exit;
}

$allowed = ['jpg','jpeg','png','webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['profile']['tmp_name']);
finfo_close($finfo);

$extMap = [
  'image/jpeg'=>'jpg',
  'image/png'=>'png',
  'image/webp'=>'webp'
];
$ext = $extMap[$mime] ?? strtolower(pathinfo($_FILES['profile']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    echo json_encode(['ok'=>false,'error'=>'Only JPG, PNG or WEBP allowed']); exit;
}
if ($_FILES['profile']['size'] > 5*1024*1024) {
    echo json_encode(['ok'=>false,'error'=>'File too large (>5MB)']); exit;
}

$useSupabase = getenv('SUPABASE_URL') && getenv('SUPABASE_SERVICE_ROLE_KEY') && getenv('SUPABASE_BUCKET_NAME');
$uploadDir = $useSupabase ? sys_get_temp_dir() : __DIR__ . '/profile';
if (!$useSupabase && !is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

$filename = 'u' . intval($uid) . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($_FILES['profile']['tmp_name'], $destPath)) {
    echo json_encode(['ok'=>false,'error'=>'Failed to save file']); exit;
}

// Try Supabase upload when env vars present
try {
    if ($useSupabase) {
        require_once __DIR__ . '/supabase_storage.php';
        $remotePath = 'profiles/' . $filename;
        $fileUrl = supabase_upload_file($destPath, $remotePath);
        // Optionally remove local file
        @unlink($destPath);
    } else {
        $base = getenv('BASE_URL') ?: 'https://web404space.com/opay';
        $fileUrl = rtrim($base, '/') . '/profile/' . $filename;
    }

    // Update DB
    $stmt = $pdo->prepare("UPDATE users SET profile=? WHERE uid=?");
    $stmt->execute([$fileUrl, $uid]);

    echo json_encode(['ok'=>true, 'url'=>$fileUrl]);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>'Upload failed: ' . $e->getMessage()]);
}
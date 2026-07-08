<?php
// supabase_storage.php
// Minimal helper to upload files to Supabase Storage using service_role key.

function supabase_upload_file($localPath, $remotePath) {
    $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: '', '/');
    $serviceKey  = getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '';
    $bucket      = getenv('SUPABASE_BUCKET_NAME') ?: '';

    if (!$supabaseUrl || !$serviceKey || !$bucket) {
        throw new Exception('Supabase storage environment variables not configured');
    }

    $endpoint = $supabaseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . ltrim($remotePath, '/');

    $fh = fopen($localPath, 'rb');
    if ($fh === false) throw new Exception('Failed to open local file');

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $serviceKey,
        'Content-Type: application/octet-stream'
    ]);
    curl_setopt($ch, CURLOPT_INFILE, $fh);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fh);

    if ($res === false || $http >= 400) {
        throw new Exception('Supabase upload failed: ' . ($err ?: "HTTP $http"));
    }

    // Public URL for object in public bucket
    $publicUrl = $supabaseUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . ltrim($remotePath, '/');
    return $publicUrl;
}

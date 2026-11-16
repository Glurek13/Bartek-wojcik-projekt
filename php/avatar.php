<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$user = signedInUser();
$file = avatarFile($user['avatar_path']);
if (!$file) {
    http_response_code(404);
    exit;
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$size = filesize($file);
if ($size === false) {
    http_response_code(404);
    exit;
}
session_write_close();
header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . $size);
readfile($file);

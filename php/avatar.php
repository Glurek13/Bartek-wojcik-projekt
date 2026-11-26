<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Publiczny odczyt awatara autora na podstawie ID. Nie wymaga sesji
// ani posiadania kodu odzyskiwania przez osobe ogladajaca komentarz.
$requested = $_GET['user_id'] ?? null;
$id = filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    // Zgodnosc ze starszymi wywolaniami avatar.php bez parametru.
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}
if (!$id) {
    http_response_code(404);
    exit;
}
$author = getRow('SELECT avatar_path FROM users WHERE id = ?', [(int) $id]);
$file = $author ? avatarFile($author['avatar_path'] ?? null) : null;
if (!$file) {
    http_response_code(404);
    exit;
}
$types = ['jpg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp', 'gif'=>'image/gif'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$size = filesize($file);
if ($size === false || !isset($types[$ext])) {
    http_response_code(404);
    exit;
}
// GIF przesylamy bez konwersji: animacja pozostaje nienaruszona.
session_write_close();
header('Content-Type: ' . $types[$ext]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Length: ' . $size);
readfile($file);

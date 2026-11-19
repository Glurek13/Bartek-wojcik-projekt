<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/footer-ads.php';

$id = $_GET['id'] ?? null;
if (!is_string($id) || preg_match('/\A[1-9][0-9]{0,9}\z/', $id) !== 1) {
    http_response_code(404);
    exit;
}

// Loading the image must not advance or lock the rotation for this session.
session_write_close();
try {
    $row = getRow(
        'SELECT image_data FROM footer_ads WHERE id = ? AND is_active = 1 ' .
        'AND OCTET_LENGTH(image_data) BETWEEN 1 AND ' . FOOTER_AD_MAX_IMAGE_BYTES,
        [$id]
    );
} catch (PDOException $error) {
    if ((string) $error->getCode() !== '42S02') {
        error_log('GameZone footer ad image query: ' . $error->getCode());
    }
    http_response_code(404);
    exit;
}

$data = $row['image_data'] ?? null;
$image = is_string($data) ? @getimagesizefromstring($data) : false;
$mime = $image['mime'] ?? '';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($data));
echo $data;

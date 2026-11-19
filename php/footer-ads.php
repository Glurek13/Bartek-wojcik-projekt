<?php
declare(strict_types=1);

const FOOTER_AD_MAX_IMAGE_BYTES = 5 * 1024 * 1024;

function footerAdAdminAllowed(?array $user): bool
{
    if (!isset($user['username'])) {
        return false;
    }
    $config = __DIR__ . '/footer-ad-admin-config.php';
    $ownerUsername = is_file($config) ? (require $config) : '';
    return is_string($ownerUsername) && $ownerUsername !== '' && $user['username'] === $ownerUsername;
}

function nextFooterAd(array $rows, ?string $previousUrl): ?array
{
    $ads = [];
    foreach ($rows as $row) {
        $url = trim((string) $row['target_url']);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true) || !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }
        if (!isset($ads[$url])) {
            $row['target_url'] = $url;
            $ads[$url] = $row;
        }
    }
    if (!$ads) {
        return null;
    }

    $urls = array_keys($ads);
    $last = array_search($previousUrl, $urls, true);
    $next = $last === false ? 0 : ($last + 1) % count($urls);
    return $ads[$urls[$next]];
}

function footerAdForPage(): ?array
{
    // On pages without a database connection (login and registration), skip the ad.
    $pdo = connection(false);
    if ($pdo === null) {
        return null;
    }

    try {
        $rows = $pdo->query(
            'SELECT id, title, target_url FROM footer_ads WHERE is_active = 1 ' .
            'AND OCTET_LENGTH(image_data) BETWEEN 1 AND ' . FOOTER_AD_MAX_IMAGE_BYTES . ' ORDER BY id ASC'
        )->fetchAll();
    } catch (PDOException $error) {
        if ((string) $error->getCode() !== '42S02') {
            error_log('GameZone footer ad query: ' . $error->getCode());
        }
        return null;
    }

    $previous = $_SESSION['gz_footer_ad_previous_url'] ?? null;
    $ad = nextFooterAd($rows, is_string($previous) ? $previous : null);
    if ($ad !== null) {
        $_SESSION['gz_footer_ad_previous_url'] = $ad['target_url'];
    }
    return $ad;
}

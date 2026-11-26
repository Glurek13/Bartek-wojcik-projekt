<?php
declare(strict_types=1);

const FOOTER_AD_MAX_IMAGE_BYTES = 5 * 1024 * 1024;

function footerAdAdminAllowed(?array $user): bool
{
    // Role are checked on the server; selecting a role in the form is not enough.
    return isAdmin($user);
}

/**
 * Pick one active advertisement at random for each complete page request.
 * Every valid row has the same chance, including ads that share a target URL.
 * This deliberately does not remember the last ad: with only two ads, a
 * no-repeat policy would simply alternate them in a predictable order.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, mixed>|null
 */
function randomFooterAd(array $rows): ?array
{
    $ads = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $url = trim((string) ($row['target_url'] ?? ''));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($id < 1 || !in_array($scheme, ['https', 'http'], true) || !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }
        $row['target_url'] = $url;
        $ads[] = $row;
    }

    if ($ads === []) {
        return null;
    }

    return $ads[random_int(0, count($ads) - 1)];
}

function footerAdForPage(): ?array
{
    // The login and registration pages can work without an active DB connection.
    $pdo = connection(false);
    if ($pdo === null) {
        return null;
    }

    try {
        $rows = $pdo->query(
            'SELECT id, title, target_url FROM footer_ads WHERE is_active = 1 ' .
            'AND OCTET_LENGTH(image_data) BETWEEN 1 AND ' . FOOTER_AD_MAX_IMAGE_BYTES
        )->fetchAll();
    } catch (PDOException $error) {
        if ((string) $error->getCode() !== '42S02') {
            error_log('GameZone footer ad query: ' . $error->getCode());
        }
        return null;
    }

    return randomFooterAd($rows);
}

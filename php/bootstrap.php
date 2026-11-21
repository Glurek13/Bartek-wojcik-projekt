<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
header('Cache-Control: no-store');

function connection(bool $open = true): ?PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    if (!$open) return null;
    $config = require __DIR__ . '/config.php';
    try {
        $pdo = new PDO(
            'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'] . ';charset=utf8mb4',
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    } catch (PDOException $error) {
        error_log('GameZone database connection error: ' . $error->getCode());
        http_response_code(503);
        exit('Nie udało się połączyć z bazą GameZone. Sprawdź, czy MySQL działa i czy dane w config.php są poprawne.');
    }
    return $pdo;
}

function db(): PDO
{
    return connection();
}

function rollbackIfNeeded(): void
{
    $pdo = connection(false);
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
}

function getRows(string $query, array $params = []): array
{
    $statement = db()->prepare($query);
    $statement->execute($params);
    return $statement->fetchAll();
}

function getRow(string $query, array $params = []): ?array
{
    $statement = db()->prepare($query);
    $statement->execute($params);
    $result = $statement->fetch();
    return $result === false ? null : $result;
}

function changeRows(string $query, array $params = []): void
{
    db()->prepare($query)->execute($params);
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $destination): void
{
    header('Location: ' . $destination, true, 303);
    exit;
}

function wantsJson(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function jsonReply(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function post(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? $value : '';
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verifyPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        if (wantsJson()) jsonReply(['error' => 'Wymagana metoda POST.'], 405);
        http_response_code(405);
        exit('Wymagana metoda POST.');
    }
    if (!hash_equals(csrf(), post('csrf'))) {
        if (wantsJson()) jsonReply(['error' => 'Sesja formularza wygasła. Zapisz niedokończone zmiany i odśwież stronę.'], 403);
        http_response_code(403);
        exit('Formularz wygasł. Otwórz stronę ponownie.');
    }
}

function csrfField(): void
{
    echo '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}

function flash(string $message, bool $error = false): void
{
    $_SESSION['gamezone_flash'] = ['message' => $message, 'error' => $error];
}

function showFlash(): void
{
    $message = $_SESSION['gamezone_flash'] ?? null;
    unset($_SESSION['gamezone_flash']);
    if (!$message) return;
    echo '<p class="notice' . ($message['error'] ? ' error' : '') . '" role="status">' . h($message['message']) . '</p>';
}

function signedInUser(): array
{
    if (empty($_SESSION['user_id'])) {
        if (wantsJson()) jsonReply(['error' => 'Sesja wygasła. Zaloguj się ponownie.'], 401);
        redirect('login.php');
    }
    $user = getRow('SELECT id, username, email, created_at, avatar_path FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['username']);
        if (wantsJson()) jsonReply(['error' => 'Sesja wygasła. Zaloguj się ponownie.'], 401);
        redirect('login.php');
    }
    return $user;
}

function signIn(int $id, string $username): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    unset($_SESSION['csrf']);
}

function textLength(string $value): int
{
    $count = preg_match_all('/./us', $value);
    return $count === false ? -1 : $count;
}

function initial(string $name): string
{
    $first = preg_match('/^./us', trim($name), $part) ? $part[0] : 'G';
    if (function_exists('mb_strtoupper')) return mb_strtoupper($first, 'UTF-8');
    return strtoupper(strtr($first, ['ą'=>'Ą','ć'=>'Ć','ę'=>'Ę','ł'=>'Ł','ń'=>'Ń','ó'=>'Ó','ś'=>'Ś','ź'=>'Ź','ż'=>'Ż']));
}

function avatarFile(?string $path): ?string
{
    if (!$path || !preg_match('~\Auploads/avatars/[a-f0-9]{32}\.(jpg|png|webp|gif)\z~', $path)) return null;
    $file = dirname(__DIR__) . '/' . $path;
    return is_file($file) && is_readable($file) ? $file : null;
}

function avatarMarkup(array $user): void
{
    echo '<span class="avatar-initial" aria-hidden="true">' . h(initial($user['username'])) . '</span>';
    if (avatarFile($user['avatar_path'])) {
        echo '<img class="avatar-image" hidden alt="" src="avatar.php?v=' . h(substr(hash('sha256', $user['avatar_path']), 0, 16)) . '">';
    }
}

function gameImage(?string $url): ?string
{
    if (!$url) return null;
    if (preg_match('~\Ahttps?://~i', $url)) return $url;
    if (preg_match('~\A[a-z0-9_-]+\.(jpg|jpeg|png|webp)\z~i', $url)) {
        $root = dirname(__DIR__);
        if (is_file($root . '/assets/' . $url) && is_readable($root . '/assets/' . $url)) return '../assets/' . $url;
        if (is_file($root . '/' . $url) && is_readable($root . '/' . $url)) return '../' . $url;
    }
    return null;
}

function formatRating(float $rating): string
{
    return rtrim(rtrim(number_format($rating, 1, '.', ''), '0'), '.') . '/10';
}

function displayDate(string $value): string
{
    $stamp = strtotime($value);
    return $stamp === false ? $value : date('d.m.Y', $stamp);
}

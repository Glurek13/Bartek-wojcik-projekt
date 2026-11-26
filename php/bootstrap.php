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

/** Czy konto ma kod, ktory wlasciciel moze odczytac i wykorzystac? */
function recoveryCodeIsPresent(int $userId): bool
{
    require_once __DIR__ . '/recovery.php';
    try {
        return accountRecoveryCode($userId) !== null;
    } catch (Throwable $error) {
        error_log('GameZone recovery availability check: ' . get_class($error));
        return false;
    }
}

function signedInUser(): array
{
    if (empty($_SESSION['user_id'])) {
        if (wantsJson()) jsonReply(['error' => 'Sesja wygasła. Zaloguj się ponownie.'], 401);
        redirect('login.php');
    }
    $user = getRow('SELECT id, username, email, created_at, avatar_path, role FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['username']);
        if (wantsJson()) jsonReply(['error' => 'Sesja wygasła. Zaloguj się ponownie.'], 401);
        redirect('login.php');
    }
    // Starsze konta bez kodu musza go utworzyc, zanim przejda dalej.
    // Profil i operacje na profilu oraz pliki awatarow pozostaja dostepne.
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($page, ['profile.php', 'actions.php', 'avatar.php'], true) && !recoveryCodeIsPresent((int) $user['id'])) {
        if (wantsJson()) jsonReply(['error' => 'Najpierw wygeneruj kod odzyskiwania w profilu.'], 403);
        redirect('profile.php?recovery=required#account-info');
    }
    return $user;
}

function isAdmin(?array $user): bool
{
    return ($user['role'] ?? 'user') === 'admin';
}

function adminOnly(): array
{
    $user = signedInUser();
    if (!isAdmin($user)) {
        if (wantsJson()) jsonReply(['error' => 'Brak uprawnień administratora.'], 403);
        http_response_code(403);
        exit('Brak uprawnień administratora.');
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

/**
 * Generuje avatar dla dowolnego autora, nie tylko aktualnie zalogowanego.
 * Brak zdjecia: domyslny fioletowy PNG z rzeczywista przezroczystoscia.
 * Nie przetwarzamy GIF-ow; zachowuja animacje w elemencie <img>.
 */
function avatarMarkup(array $user): void
{
    echo '<img class="avatar-fallback" src="../assets/default-avatar.png" alt="" width="40" height="40" loading="lazy">';

    $ownerId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $avatarPath = $user['avatar_path'] ?? null;
    if ($ownerId && is_string($avatarPath) && avatarFile($avatarPath)) {
        $file = avatarFile($avatarPath);
        $version = substr(hash('sha256', $avatarPath . ':' . ($file ? (string) filemtime($file) : '')), 0, 16);
        echo '<img class="avatar-image" hidden alt="" loading="eager" decoding="async"'
           . ' src="avatar.php?user_id=' . h($ownerId) . '&amp;v=' . h($version) . '">';
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

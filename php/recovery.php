<?php
declare(strict_types=1);

/**
 * Kod odzyskiwania jest automatyczny, losowy (128 bitow), jednokrotnego uzycia.
 * W MySQL przechowujemy hash kodu oraz wersje zaszyfrowana dla wlasciciela.
 * Klucz szyfrowania zapisujemy POZA katalogiem publicznym (zwykle C:\xampp\).
 * Nie zmieniaj/nie usuwaj tego klucza w czasie aktualizacji GameZone.
 */
function recoveryKeyPath(): string
{
    $configured = getenv('GAMEZONE_RECOVERY_KEY_FILE');
    if (is_string($configured) && $configured !== '') return $configured;

    $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (is_string($root) && $root !== '' && is_dir($root)) {
        return dirname((string) realpath($root)) . DIRECTORY_SEPARATOR . 'gamezone_recovery_key.php';
    }
    $dir = __DIR__;
    while ($dir !== dirname($dir)) {
        if (strcasecmp(basename($dir), 'htdocs') === 0) {
            return dirname($dir) . DIRECTORY_SEPARATOR . 'gamezone_recovery_key.php';
        }
        $dir = dirname($dir);
    }
    throw new RuntimeException('Nie ustalono bezpiecznego miejsca na klucz. Ustaw GAMEZONE_RECOVERY_KEY_FILE poza folderem strony.');
}

function recoverySecretKey(): string
{
    static $key;
    if (is_string($key)) return $key;
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('Brak rozszerzenia OpenSSL w PHP.');
    }
    $file = recoveryKeyPath();
    if (!is_file($file)) {
        $secret = bin2hex(random_bytes(32));
        $phpSource = '<?php return ' . var_export($secret, true) . ";\n";
        $handle = @fopen($file, 'x');
        if (is_resource($handle)) {
            $written = fwrite($handle, $phpSource);
            fclose($handle);
            @chmod($file, 0600);
            if ($written !== strlen($phpSource)) {
                @unlink($file);
                throw new RuntimeException('Nie udalo sie zapisac klucza odzyskiwania.');
            }
        }
    }
    if (!is_file($file) || !is_readable($file)) {
        throw new RuntimeException('Nie mozna odczytac klucza odzyskiwania poza katalogiem strony.');
    }
    $secretHex = require $file;
    if (!is_string($secretHex) || !preg_match('/\A[a-f0-9]{64}\z/D', $secretHex)) {
        throw new RuntimeException('Klucz odzyskiwania jest uszkodzony. Przywroc go z kopii.');
    }
    $key = hex2bin($secretHex);
    if (!is_string($key)) throw new RuntimeException('Nieprawidlowy klucz odzyskiwania.');
    return $key;
}

function normalizedRecoveryCode(string $input): ?string
{
    $code = strtolower(str_replace(['-', ' '], '', trim($input)));
    return preg_match('/\A[a-f0-9]{32}\z/D', $code) ? $code : null;
}

function formatRecoveryCode(string $code): string
{
    return strtoupper(implode('-', str_split($code, 4)));
}

function encryptRecoveryCode(string $normalized): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($normalized, 'aes-256-gcm', recoverySecretKey(), OPENSSL_RAW_DATA, $iv, $tag, 'gamezone-recovery-v1', 16);
    if (!is_string($cipher)) throw new RuntimeException('Blad szyfrowania kodu odzyskiwania.');
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decryptRecoveryCode(string $encoded): ?string
{
    if (!str_starts_with($encoded, 'v1:')) return null;
    $data = base64_decode(substr($encoded, 3), true);
    if (!is_string($data) || strlen($data) !== 12 + 16 + 32) return null;
    $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', recoverySecretKey(), OPENSSL_RAW_DATA,
        substr($data, 0, 12), substr($data, 12, 16), 'gamezone-recovery-v1');
    return is_string($plain) ? normalizedRecoveryCode($plain) : null;
}

/** Automatycznie generuje lub wymienia kod przypisany do konta. */
function issueRecoveryCode(int $userId): string
{
    if ($userId < 1) throw new InvalidArgumentException('Bledny identyfikator konta.');
    $code = bin2hex(random_bytes(16));
    $encrypted = encryptRecoveryCode($code);
    changeRows(
        'INSERT INTO account_recovery_codes (user_id, code_hash, code_encrypted, created_at) VALUES (?, ?, ?, NOW()) '
        . 'ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), code_encrypted = VALUES(code_encrypted), created_at = NOW()',
        [$userId, hash('sha256', $code), $encrypted]
    );
    return formatRecoveryCode($code);
}

/**
 * Wyswietla juz istniejacy kod zalogowanemu wlascicielowi.
 * UWAGA: Nigdy nie generuje kodu przez zwykle GET / otwarcie profilu.
 * Legacy konta musza kliknac "Generuj kod" (chroniony CSRF POST).
 */
function accountRecoveryCode(int $userId): ?string
{
    $record = getRow('SELECT code_hash, code_encrypted FROM account_recovery_codes WHERE user_id = ?', [$userId]);
    if (!$record || !is_string($record['code_encrypted'] ?? null) || $record['code_encrypted'] === '') {
        return null;
    }
    $plain = decryptRecoveryCode($record['code_encrypted']);
    return $plain !== null && hash_equals((string) $record['code_hash'], hash('sha256', $plain))
        ? formatRecoveryCode($plain) : null;
}

/**
 * Pierwsze klikniecie tworzy kod dla starszego konta; kolejne go NIE zmieniaja.
 * Blokada na rekordzie users chroni przed podwojnym kliknieciem/wyścigiem.
 */
function generateRecoveryCodeOnce(int $userId): string
{
    $ownTransaction = !db()->inTransaction();
    if ($ownTransaction) db()->beginTransaction();
    try {
        $account = getRow('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        if (!$account) throw new DomainException('Nie znaleziono konta.');
        $existing = accountRecoveryCode($userId);
        $result = $existing ?? issueRecoveryCode($userId);
        if ($ownTransaction) db()->commit();
        return $result;
    } catch (Throwable $err) {
        if ($ownTransaction && db()->inTransaction()) db()->rollBack();
        throw $err;
    }
}

function validRecoveryPassword(string $password, bool $admin): bool
{
    return textLength($password) >= ($admin ? 12 : 8) && strlen($password) <= 72;
}

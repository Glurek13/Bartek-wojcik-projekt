<?php
declare(strict_types=1);
// Bezpieczny bootstrap: działa TYLKO z terminala XAMPP; z przeglądarki zwraca 403.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Tylko terminal PHP CLI.');
}
require __DIR__ . '/bootstrap.php';
if (!defined('STDIN')) exit("Brak terminala.\n");
try {
    $count = getRow("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
    if ((int) ($count['c'] ?? 0) !== 0) {
        exit("Administrator już istnieje. Następnych dodaje się zaproszeniem z panelu admina.\n");
    }
    echo "Pierwszy administrator GameZone\n";
    echo "Najpierw utwórz zwykłe konto na stronie (jeśli go nie masz).\n";
    echo "E-mail istniejącego konta: ";
    $email = trim((string) fgets(STDIN));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) exit("Niepoprawny e-mail.\n");
    echo "Hasło tego konta (na Windows może być widoczne podczas wpisywania): ";
    $plain = rtrim((string) fgets(STDIN), "\r\n");
    $u = getRow('SELECT id, `password` FROM users WHERE email = ? LIMIT 1', [strtolower($email)]);
    if (!$u || !$u['password']) exit("Nieprawidłowe dane konta.\n");
    $stored = (string) $u['password'];
    $ok = password_get_info($stored)['algo'] !== null ? password_verify($plain, $stored) : hash_equals($stored, $plain);
    if (!$ok) exit("Nieprawidłowe dane konta.\n");
    db()->beginTransaction();
    $count = getRow("SELECT id FROM users WHERE role = 'admin' LIMIT 1 FOR UPDATE");
    if ($count) { db()->rollBack(); exit("Administrator został już utworzony.\n"); }
    changeRows("UPDATE users SET role = 'admin', `password` = ? WHERE id = ?", [password_hash($plain, PASSWORD_DEFAULT), (int) $u['id']]);
    db()->commit();
    echo "OK: konto jest administratorem. Wejdź na php/login-admin.php.\n";
    echo "Zalecenie: ustaw w profilu silne, unikalne hasło (co najmniej 12 znaków).\n";
} catch (Throwable $error) {
    rollbackIfNeeded();
    fwrite(STDERR, "Błąd: sprawdź import migracji adminów i ustawienia bazy.\n");
    exit(1);
}

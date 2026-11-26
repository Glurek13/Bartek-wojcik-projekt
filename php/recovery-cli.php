<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrypt dostepny tylko z lokalnej konsoli PHP.');
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/recovery.php';

fwrite(STDOUT, "GameZone — pomoc w odzyskiwaniu konta\nWpisz nazwe uzytkownika po zweryfikowaniu wlasciciela konta: ");
$name = trim((string) fgets(STDIN));
if ($name === '' || strlen($name) > 200) exit("Nieprawidlowa nazwa.\n");
$account = getRow('SELECT id FROM users WHERE username = ? LIMIT 1', [$name]);
if (!$account) exit("Nie znaleziono konta.\n");
try {
    $code = issueRecoveryCode((int) $account['id']);
    fwrite(STDOUT, "Nowy kod odzyskiwania (pokaz tylko wlascicielowi): {$code}\n");
    fwrite(STDOUT, "Otworz reset-password.php i wpisz nazwe, kod i nowe haslo.\n");
} catch (Throwable $err) {
    fwrite(STDERR, "Blad. Najpierw zaimportuj sql/reset_hasla_migracja.sql.\n");
    exit(1);
}

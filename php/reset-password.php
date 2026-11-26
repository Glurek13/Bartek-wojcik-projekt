<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/recovery.php';
require __DIR__ . '/layout.php';

// Strona dostepna rowniez bez sesji logowania; sam kod odzyskiwania jest konieczny.
$type = ($_GET['type'] ?? $_POST['account_type'] ?? 'user') === 'admin' ? 'admin' : 'user';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyPost();
    $username = trim(post('username'));
    $code = normalizedRecoveryCode(post('recovery_code'));
    $new = post('new_password');
    $confirm = post('confirm_password');
    $limit = $_SESSION['gz_recovery_limit'] ?? ['start' => time(), 'count' => 0];
    if (time() - (int) ($limit['start'] ?? 0) > 900) {
        $limit = ['start' => time(), 'count' => 0];
    }
    try {
        if ((int) ($limit['count'] ?? 0) >= 6) {
            throw new DomainException('Zbyt wiele nieudanych prób. Spróbuj ponownie za 15 minut.');
        }
        if ($username === '' || textLength($username) > 50 || !$code ||
            $new !== $confirm || !validRecoveryPassword($new, $type === 'admin')) {
            throw new DomainException('Sprawdź nazwę użytkownika, kod i oba pola nowego hasła (min. 8 znaków, administrator: 12).');
        }
        db()->beginTransaction();
        $account = getRow('SELECT id, role FROM users WHERE username = ? LIMIT 1 FOR UPDATE', [$username]);
        $stored = $account ? getRow('SELECT code_hash FROM account_recovery_codes WHERE user_id = ? FOR UPDATE', [(int) $account['id']]) : null;
        if (!$account || $account['role'] !== $type || !$stored ||
            !hash_equals((string) $stored['code_hash'], hash('sha256', $code))) {
            throw new DomainException('Nieprawidłowa nazwa użytkownika, rodzaj konta lub kod odzyskiwania.');
        }
        changeRows('UPDATE users SET `password` = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $account['id']]);
        changeRows('DELETE FROM account_recovery_codes WHERE user_id = ?', [(int) $account['id']]);
        db()->commit();
        unset($_SESSION['gz_login_fail_since'], $_SESSION['gz_login_fail_count'], $_SESSION['gz_recovery_limit']);
        // Kod jest zuzyty. Po zalogowaniu trzeba kliknac Generuj kod w profilu.
        flash('Hasło zmienione. Zaloguj się nowym hasłem i kliknij Generuj kod w Informacjach o koncie.');
        redirect('login-' . $type . '.php');
    } catch (DomainException $err) {
        rollbackIfNeeded();
        if ((int) ($limit['count'] ?? 0) < 6) $limit['count']++;
        $_SESSION['gz_recovery_limit'] = $limit;
        flash($err->getMessage(), true);
    } catch (Throwable $err) {
        rollbackIfNeeded();
        error_log('GameZone password recovery: ' . get_class($err));
        flash('Nie udało się zresetować hasła. Sprawdź, czy zaimportowano migrację SQL.', true);
    }
    redirect('reset-password.php?type=' . $type);
}

pageStart('Resetowanie hasła', 'login');
?>
<main class="auth-page">
    <h1>🔑 Resetowanie hasła</h1>
    <div class="account-tabs" aria-label="Rodzaj konta">
        <a href="reset-password.php?type=user"<?= $type === 'user' ? ' aria-current="page"' : '' ?>>🎮 Użytkownik</a>
        <a href="reset-password.php?type=admin"<?= $type === 'admin' ? ' aria-current="page"' : '' ?>>🛡️ Administrator</a>
    </div>
    <?php showFlash(); ?>
    <form class="panel stack" method="post" action="reset-password.php?type=<?= h($type) ?>">
        <?php csrfField(); ?>
        <input type="hidden" name="account_type" value="<?= h($type) ?>">
        <h2>Ustaw nowe hasło</h2>
        <p class="muted">Podaj dokładną nazwę użytkownika oraz kod odzyskiwania wyświetlany w profilu w sekcji „Informacje o koncie”. Sama nazwa nie wystarcza do zmiany hasła.</p>
        <label for="resetUsername">Nazwa użytkownika</label>
        <input id="resetUsername" name="username" type="text" maxlength="50" autocomplete="username" required>
        <label for="resetRecoveryCode">Kod odzyskiwania</label>
        <input id="resetRecoveryCode" name="recovery_code" type="text" inputmode="text" maxlength="45" autocomplete="off" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX" required>
        <?php passwordField('resetNewPassword', 'new_password', $type === 'admin' ? 'Nowe hasło (minimum 12 znaków)' : 'Nowe hasło (minimum 8 znaków)', 'new-password', true); ?>
        <?php passwordField('resetConfirmPassword', 'confirm_password', 'Powtórz nowe hasło', 'new-password', true); ?>
        <button class="primary" type="submit">Zmień hasło</button>
        <p class="muted">Starsze konto bez kodu? Jeśli pamiętasz obecne hasło, zaloguj się i zmień je w profilu bez kodu, a następnie kliknij Generuj kod. Jeśli nie pamiętasz hasła, skontaktuj się z administratorem GameZone, który po potwierdzeniu Twojej tożsamości może wystawić kod odzyskiwania.</p>
        <p><a href="login-<?= h($type) ?>.php">← Wróć do logowania</a></p>
    </form>
</main>
<?php pageEnd(); ?>

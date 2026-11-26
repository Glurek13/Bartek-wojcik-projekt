<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
$type = 'user';
if (isset($_SESSION['gz_login_fail_since']) && (time() - (int) $_SESSION['gz_login_fail_since']) > 1800) {
    unset($_SESSION['gz_login_fail_since'], $_SESSION['gz_login_fail_count']);
}
$showReset = (int) ($_SESSION['gz_login_fail_count'] ?? 0) >= 3;
pageStart('Logowanie', 'login');
?>
<main class="auth-page">
    <h1>Logowanie do GameZone</h1>

    <?php showFlash(); ?>
    <form class="panel" method="post" action="actions.php">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="account_type" value="<?= h($type) ?>">
        <h2><?= $type === 'admin' ? 'Panel administratora' : 'Konto użytkownika' ?></h2>
        <label for="email">Adres e-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" maxlength="190" required>
        <?php passwordField('password', 'password', 'Hasło', 'current-password'); ?>
        <button class="primary" type="submit">Zaloguj się jako <?= $type === 'admin' ? 'administrator' : 'użytkownik' ?></button>
        <?php if ($showReset): ?>
            <div class="recovery-prompt" role="status">
                <strong>Nie pamiętasz hasła?</strong>
                <p>Po 3 nieudanych próbach możesz odzyskać dostęp za pomocą kodu przypisanego do Twojego konta.</p>
                <a class="secondary" href="reset-password.php?type=<?= h($type) ?>">🔑 Zresetuj hasło</a>
            </div>
        <?php endif; ?>
        <?php if ($type === 'admin'): ?>
            <p class="muted">Samo wybranie tej opcji nie daje uprawnień. Konto musi mieć rolę administratora w bazie.</p>
        <?php endif; ?>
        <p>Nie masz konta? <a href="register-user.php">Zarejestruj się</a>.</p>
    </form>
</main>
<?php pageEnd(); ?>

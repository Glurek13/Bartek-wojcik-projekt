<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
$type = 'admin';
pageStart('Rejestracja', 'register');
?>
<main class="auth-page">
    <h1>Rejestracja <?= $type === 'admin' ? 'administratora' : 'użytkownika' ?></h1>

    <?php showFlash(); ?>
    <form class="panel" method="post" action="actions.php">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="account_type" value="<?= h($type) ?>">
        <label for="username">Nazwa użytkownika</label>
        <input id="username" name="username" minlength="3" maxlength="50" autocomplete="username" required>
        <label for="email">Adres e-mail</label>
        <input id="email" name="email" type="email" autocomplete="email" maxlength="190" required>
        <?php passwordField('password', 'password', $type === 'admin' ? 'Hasło (minimum 12 znaków)' : 'Hasło (minimum 8 znaków)', 'new-password', true); ?>
        <?php if ($type === 'admin'): ?>
            <label for="inviteCode">Jednorazowy kod zaproszenia od administratora</label>
            <input id="inviteCode" name="invite_code" required minlength="64" maxlength="64" pattern="[A-Fa-f0-9]{64}" autocomplete="off" placeholder="64 znaki kodu zaproszenia">
            <p class="muted">Kod jest ważny 24 godziny i może zostać wykorzystany tylko raz. Pierwszego administratora zakłada się lokalnie w XAMPP.</p>
        <?php endif; ?>
        <button class="primary" type="submit">Załóż konto <?= $type === 'admin' ? 'administratora' : 'użytkownika' ?></button>
        <p>Masz już konto? <a href="login-admin.php">Zaloguj się</a>.</p>
    </form>
</main>
<?php pageEnd(); ?>

<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
pageStart('Logowanie', 'login');
?>
<main class="auth-page">
    <h1>Logowanie</h1>
    <?php showFlash(); ?>
    <form class="panel" method="post" action="actions.php">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="login">
        <label for="email">Adres e-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" maxlength="190" required>
        <?php passwordField('password', 'password', 'Hasło', 'current-password'); ?>
        <button class="primary" type="submit">Zaloguj się</button>
        <p>Nie masz konta? <a href="register.php">Zarejestruj się</a>.</p>
    </form>
</main>
<?php pageEnd(); ?>

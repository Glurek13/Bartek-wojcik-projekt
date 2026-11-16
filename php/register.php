<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
pageStart('Rejestracja', 'register');
?>
<main class="auth-page">
    <h1>Utwórz konto</h1>
    <?php showFlash(); ?>
    <form class="panel" method="post" action="actions.php">
        <?php csrfField(); ?>
        <input type="hidden" name="action" value="register">
        <label for="username">Nazwa użytkownika</label>
        <input id="username" name="username" minlength="3" maxlength="50" autocomplete="username" required>
        <label for="email">Adres e-mail</label>
        <input id="email" name="email" type="email" autocomplete="email" maxlength="190" required>
        <?php passwordField('password', 'password', 'Hasło (minimum 8 znaków)', 'new-password', true); ?>
        <button class="primary" type="submit">Zarejestruj się</button>
        <p>Masz już konto? <a href="login.php">Zaloguj się</a>.</p>
    </form>
</main>
<?php pageEnd(); ?>

<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
// Starsze linki z parametrem type kierujemy na odpowiednie strony.
if (isset($_GET['type']) && in_array($_GET['type'], ['user', 'admin'], true)) {
    redirect('login-' . $_GET['type'] . '.php');
}
pageStart('Logowanie', 'account-choice');
?>
<main class="auth-page account-chooser">
    <h1>Logowanie do GameZone</h1>
    <p class="muted chooser-lead">Wybierz rodzaj konta. Każdy rodzaj ma osobną stronę.</p>
    <?php showFlash(); ?>
    <div class="account-options">
        <a class="account-option" href="login-user.php">
            <span class="account-option-icon" aria-hidden="true">🎮</span>
            <strong>Użytkownik</strong>
            <span>Zaloguj się jako gracz</span>
        </a>
        <a class="account-option" href="login-admin.php">
            <span class="account-option-icon" aria-hidden="true">🛡️</span>
            <strong>Administrator</strong>
            <span>Zaloguj się jako administrator</span>
        </a>
    </div>
    <p class="muted chooser-note">Administrator musi posiadać odpowiednią rolę w bazie. Rejestracja administratora wymaga jednorazowego zaproszenia.</p>
    <p class="chooser-switch"><a href="register.php">Nie masz jeszcze konta? Rejestracja</a></p>
</main>
<?php pageEnd(); ?>

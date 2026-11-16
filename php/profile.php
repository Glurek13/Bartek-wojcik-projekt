<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/reviews.php';
$user = signedInUser();
$favorites = getRows('SELECT g.id, g.name, g.image, g.rating, c.name AS category FROM favorites f JOIN games g ON g.id = f.game_id JOIN categories c ON c.id = g.category_id WHERE f.user_id = ? ORDER BY f.created_at DESC, g.id DESC', [$user['id']]);
$reviews = fetchReviews((int) $user['id'], (int) $user['id']);
$average = $reviews ? formatRating(array_sum(array_column($reviews, 'rating')) / count($reviews)) : '—';
pageStart('Mój profil', 'profile', $user);
?>
<main class="profile">
    <section class="profile-hero">
        <div class="identity">
            <div class="avatar avatar-large" aria-hidden="true"><?php avatarMarkup($user); ?></div>
            <div>
                <p class="category">Konto gracza</p>
                <h1><?= h($user['username']) ?></h1>
                <p><?= h($user['email']) ?></p>
                <p class="muted">Na GameZone od <?= h(displayDate($user['created_at'])) ?></p>
            </div>
        </div>
        <dl class="stats">
            <div><dt>Ulubione gry</dt><dd id="favoriteCount"><?= count($favorites) ?></dd></div>
            <div><dt>Recenzje</dt><dd><?= count($reviews) ?></dd></div>
            <div><dt>Średnia ocen</dt><dd><?= h($average) ?></dd></div>
        </dl>
    </section>

    <div class="profile-grid">
        <div class="profile-stack">
            <section class="panel" id="settings">
                <h2>Edytuj profil</h2>
                <?php if (isset($_GET['rename'])) showFlash(); ?>
                <form class="stack" method="post" action="actions.php">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="rename">
                    <label for="profileName">Nazwa użytkownika</label>
                    <input id="profileName" name="username" minlength="3" maxlength="50" autocomplete="username" required value="<?= h($user['username']) ?>">
                    <button type="submit" class="primary">Zapisz nazwę</button>
                </form>
            </section>

            <section class="panel" id="photo">
                <h2>Zdjęcie profilowe</h2>
                <?php if (isset($_GET['avatar'])) showFlash(); ?>
                <p class="muted">JPG, PNG lub WEBP do 2 MB.</p>
                <form class="stack" method="post" action="actions.php" enctype="multipart/form-data">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="avatar">
                    <label for="avatarFile">Wybierz zdjęcie</label>
                    <input id="avatarFile" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <button type="submit" class="primary">Zapisz zdjęcie</button>
                </form>
            </section>

            <section class="panel" id="password-settings">
                <h2>Zmień hasło</h2>
                <?php if (isset($_GET['password'])) showFlash(); ?>
                <form class="stack" method="post" action="actions.php">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="password">
                    <?php passwordField('currentPassword', 'current_password', 'Obecne hasło', 'current-password'); ?>
                    <?php passwordField('newPassword', 'new_password', 'Nowe hasło (minimum 8 znaków)', 'new-password', true); ?>
                    <?php passwordField('confirmPassword', 'confirm_password', 'Powtórz nowe hasło', 'new-password', true); ?>
                    <button type="submit" class="primary">Zmień hasło</button>
                </form>
            </section>

            <section class="panel">
                <h2>Wylogowanie</h2>
                <form method="post" action="actions.php">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="danger">Wyloguj się</button>
                </form>
            </section>
        </div>
        <section class="panel" id="favorite-games">
            <h2 id="favoriteHeading" tabindex="-1">♥ Moje ulubione</h2>
            <?php if (isset($_GET['unfavorite'])) showFlash(); ?>
            <div class="favorite-list" id="profileFavorites">
                <?php foreach ($favorites as $game): $image = gameImage($game['image']); ?>
                <article class="favorite-item" data-profile-game="<?= h($game['id']) ?>">
                    <?php if ($image): ?><img loading="lazy" src="<?= h($image) ?>" alt=""><?php endif; ?>
                    <div><span class="category"><?= h($game['category']) ?></span><h3><?= h($game['name']) ?></h3><span class="rating">⭐ <?= h(formatRating((float) $game['rating'])) ?></span></div>
                    <form method="post" action="actions.php" data-live-action="favorite">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="unfavorite">
                        <input type="hidden" name="game_id" value="<?= h($game['id']) ?>">
                        <button class="danger" type="submit">Usuń</button>
                    </form>
                </article>
                <?php endforeach; ?>
                <p class="muted" id="favoritesEmpty"<?= $favorites ? ' hidden' : '' ?>>Nie masz jeszcze ulubionych gier.</p>
            </div>
            <a href="index.php#games">Przeglądaj gry →</a>
        </section>
    </div>

    <section class="panel my-reviews" id="my-reviews">
        <h2>Moje recenzje</h2>
        <?php if (isset($_GET['review'])) showFlash(); ?>
        <?php reviewSetupNotice(); ?>
        <div class="reviews-grid"><?php foreach ($reviews as $review) reviewCard($review, (int) $user['id'], 'profile'); ?></div>
        <?php if (!$reviews): ?><p class="muted">Nie masz jeszcze recenzji.</p><?php endif; ?>
        <a href="index.php#reviews">Dodaj recenzję →</a>
    </section>
</main>
<?php pageEnd(); ?>

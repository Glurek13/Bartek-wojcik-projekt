<?php

function pageStart(string $title, string $page, ?array $user = null): void
{
    ?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — GameZone</title>
    <link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
    <link rel="stylesheet" href="../css/style.css?v=3">
    <script src="../js/script.js?v=3" defer></script>
</head>
<body>
<header class="site-header">
    <a class="logo" href="index.php"><img src="../assets/logo.svg" alt="" width="34" height="34">GameZone</a>
    <?php if ($user): ?>
    <nav aria-label="Menu główne">
        <a href="index.php">Strona główna</a>
        <a href="index.php#games">Gry</a>
        <a href="index.php#reviews">Recenzje</a>
        <a href="index.php#favorites">Ulubione</a>
        <a href="index.php#about">O nas</a>
    </nav>
    <div class="header-actions">
        <?php if ($page === 'home'): ?>
        <form class="search" id="searchForm" role="search">
            <input id="searchInput" type="search" placeholder="Szukaj gry..." aria-label="Szukaj gry">
            <button type="submit" aria-label="Szukaj">🔍</button>
        </form>
        <?php endif; ?>
        <a class="avatar avatar-link" href="profile.php" aria-label="Mój profil" title="Mój profil"<?= $page === 'profile' ? ' aria-current="page"' : '' ?>>
            <?php avatarMarkup($user); ?>
        </a>
    </div>
    <?php else: ?>
    <nav aria-label="Konto">
        <a href="login.php"<?= $page === 'login' ? ' aria-current="page"' : '' ?>>Logowanie</a>
        <a href="register.php"<?= $page === 'register' ? ' aria-current="page"' : '' ?>>Rejestracja</a>
    </nav>
    <?php endif; ?>
</header>
    <?php
}

function pageEnd(): void
{
    echo '<footer>© ' . h(date('Y')) . ' GameZone</footer><p id="interactionStatus" class="interaction-status" role="status" aria-atomic="true"></p></body></html>';
}

function reviewCard(array $review, int $viewer, string $from, bool $standalone = false): void
{
    $id = (int) $review['id'];
    $ownsReview = $review['user_id'] !== null && (int) $review['user_id'] === $viewer;
    $liked = (bool) $review['liked'];
    ?>
    <article class="review review-interactive" id="review-<?= h($id) ?>">
        <div class="game-top">
            <h3><?= h($review['game_name']) ?></h3>
            <strong class="rating">⭐ <?= h(formatRating((float) $review['rating'])) ?></strong>
        </div>
        <p><?= h($review['content']) ?></p>
        <small><?= isset($review['author']) ? h($review['author']) . ' · ' : '' ?><?= h(displayDate($review['created_at'])) ?></small>
        <?php if (reviewInteractionsAvailable()): ?>
        <div class="review-actions">
            <form method="post" action="review-actions.php" data-live-action="like">
                <?php reviewActionFields('like', $id, $from); ?>
                <input type="hidden" name="liked" value="<?= $liked ? '0' : '1' ?>">
                <input type="hidden" name="view" value="<?= $standalone ? 'detail' : 'list' ?>">
                <button type="submit" class="review-button<?= $liked ? ' is-liked' : '' ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>">
                    <span data-like-icon aria-hidden="true"><?= $liked ? '♥' : '♡' ?></span> Polubienia <span class="review-count"><?= h($review['like_count']) ?></span>
                </button>
            </form>
            <a class="review-button" href="<?= h(reviewPageUrl($id, $from, [], '#comments')) ?>">Komentarze <span class="review-count"><?= h($review['comment_count']) ?></span></a>
        </div>
        <?php endif; ?>
        <?php if ($ownsReview): ?>
        <div class="review-owner-actions">
            <a href="<?= h(reviewPageUrl($id, $from, ['edit' => 1], '#edit-review')) ?>">Edytuj</a>
            <a class="review-delete-link" href="<?= h(reviewPageUrl($id, $from, ['delete' => 1], '#delete-review')) ?>">Usuń recenzję</a>
        </div>
        <?php endif; ?>
    </article>
    <?php
}

function reviewSetupNotice(): void
{
    if (!reviewInteractionsAvailable()) {
        echo '<p class="notice" role="status">Recenzje są dostępne. Aby włączyć polubienia i komentarze, zaimportuj do bazy GameZone plik <strong>rozbudowane_recenzje_migracja.sql</strong>.</p>';
    }
}

function passwordField(string $id, string $name, string $label, string $autocomplete, bool $isNew = false): void
{
    ?>
    <label for="<?= h($id) ?>"><?= h($label) ?></label>
    <div class="password-field">
        <input id="<?= h($id) ?>" name="<?= h($name) ?>" type="password" autocomplete="<?= h($autocomplete) ?>"<?= $isNew ? ' minlength="8" maxlength="255"' : '' ?> required>
        <button class="password-toggle" type="button" data-toggle-password="<?= h($id) ?>" aria-controls="<?= h($id) ?>">Pokaż hasło</button>
    </div>
    <?php
}

<?php

require_once __DIR__ . '/footer-ads.php';
require_once __DIR__ . '/contact-config.php';

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
    <link rel="stylesheet" href="../css/style.css?v=kontakt-rowne-1">
    <link rel="stylesheet" href="../css/footer-ad.css?v=gif6">
    <script src="../js/script.js?v=avatars-v2" defer></script>
</head>
<body>
<header class="site-header">
    <a class="logo" href="<?= $user ? 'index.php' : 'account-choice.php' ?>"><img src="../assets/logo.svg" alt="" width="34" height="34">GameZone</a>
    <?php if ($user): ?>
    <nav aria-label="Menu główne">
        <a href="index.php">Strona główna</a>
        <a href="index.php#games">Gry</a>
        <a href="news.php"<?= $page === 'news' ? ' aria-current="page"' : '' ?>>Aktualności</a>
        <a href="index.php#reviews">Recenzje</a>
        <a href="index.php#favorites">Ulubione</a>
        <a href="index.php#about">O nas</a>
        <?php if (isAdmin($user)): ?>
        <a href="admin.php"<?= $page === 'admin' ? ' aria-current="page"' : '' ?>>Panel admina</a>
        <a href="footer-ad-admin.php"<?= $page === 'ad-admin' ? ' aria-current="page"' : '' ?>>Reklamy</a>
        <?php endif; ?>
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
    $ad = footerAdForPage();
    ?>
<footer class="gz-site-footer">
    <section class="gz-contact" aria-labelledby="gz-contact-heading">
        <h2 id="gz-contact-heading">Kontakt z nami</h2>
        <div class="gz-contact__items">
            <div class="gz-contact__item">
                <img class="gz-contact__icon gz-contact__icon--mail" src="../assets/contact-email.svg" alt="" width="42" height="42" loading="lazy">
                <span class="gz-contact__email">gamezone6666@gmail.com</span>
            </div>
            <div class="gz-contact__item">
                <?php if (GAMEZONE_FACEBOOK_GROUP_URL !== ''): ?>
                    <a class="gz-contact__icon-link" href="<?= h(GAMEZONE_FACEBOOK_GROUP_URL) ?>" target="_blank" rel="noopener noreferrer"
                       aria-label="Otwórz grupę GameZone na Facebooku" title="Kliknij ikonę, aby otworzyć grupę GameZone">
                        <img class="gz-contact__icon" src="../assets/contact-facebook.svg" alt="Facebook" width="42" height="42" loading="lazy">
                    </a>
                    <span>Facebook — nasza grupa</span>
                <?php else: ?>
                    <img class="gz-contact__icon" src="../assets/contact-facebook.svg" alt="" width="42" height="42" loading="lazy">
                    <span class="gz-contact__facebook-unset">Facebook — nasza grupa (link wkrótce)</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <p class="gz-site-footer__copyright">© <?= h(date('Y')) ?> GameZone</p>
</footer>
<p id="interactionStatus" class="interaction-status" role="status" aria-atomic="true"></p>
<?php if ($ad !== null): ?>
<aside class="gz-footer-ad" aria-label="Reklama">
    <a class="gz-footer-ad__link" href="<?= h($ad['target_url']) ?>">
        <img class="gz-footer-ad__image" src="<?= h('footer-ad-image.php?id=' . $ad['id']) ?>"
             alt="" width="128" height="64" loading="lazy" decoding="async">
        <span class="gz-footer-ad__copy">
            <span class="gz-footer-ad__label">Reklama</span>
            <strong class="gz-footer-ad__title"><?= h($ad['title']) ?></strong>
        </span>
        <span class="gz-footer-ad__cta" aria-hidden="true">
            <span>Otwórz</span> ↗
        </span>
    </a>
</aside>
<?php endif; ?>
</body>
</html>
    <?php
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
        <div class="review-author" aria-label="Autor opinii">
            <span class="avatar avatar-small" aria-hidden="true"><?php avatarMarkup([
                'id' => $review['user_id'],
                'avatar_path' => $review['author_avatar_path'] ?? null,
            ]); ?></span>
            <span class="author-meta">
                <strong><?= h($review['display_author'] ?? $review['author'] ?? 'Użytkownik') ?></strong>
                <small><?= h(displayDate($review['created_at'])) ?></small>
            </span>
        </div>
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
        <div class="review-owner-actions" aria-label="Zarządzaj swoją opinią">
            <details class="review-owner-menu review-owner-editor">
                <summary class="review-button">✏️ Edytuj opinię</summary>
                <form class="review-inline-form stack" method="post" action="review-actions.php">
                    <?php reviewActionFields('edit', $id, $from); ?>
                    <input type="hidden" name="view" value="list">
                    <label for="ownerRating-<?= $id ?>">Twoja ocena (1–10)</label>
                    <input id="ownerRating-<?= $id ?>" name="rating" type="number" min="1" max="10" step="0.1" value="<?= h($review['rating']) ?>" required>
                    <label for="ownerContent-<?= $id ?>">Treść opinii</label>
                    <textarea id="ownerContent-<?= $id ?>" name="content" rows="5" minlength="5" maxlength="3000" required><?= h($review['content']) ?></textarea>
                    <button class="primary" type="submit">Zapisz zmiany</button>
                </form>
            </details>
            <details class="review-owner-menu review-owner-delete">
                <summary class="review-button review-delete-link">🗑️ Usuń opinię</summary>
                <form class="review-inline-form stack" method="post" action="review-actions.php" data-confirm="Czy na pewno usunąć swoją opinię? Usunięte zostaną również jej komentarze i polubienia.">
                    <?php reviewActionFields('delete', $id, $from); ?>
                    <input type="hidden" name="view" value="list">
                    <p class="muted">Usunięcie jest nieodwracalne. Znikną również komentarze i polubienia tej opinii.</p>
                    <button class="danger" type="submit">Tak, usuń moją opinię</button>
                </form>
            </details>
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
        <input id="<?= h($id) ?>" name="<?= h($name) ?>" type="password" autocomplete="<?= h($autocomplete) ?>"<?= $isNew ? ' minlength="8" maxlength="72"' : '' ?> required>
        <button class="password-toggle" type="button" data-toggle-password="<?= h($id) ?>" aria-controls="<?= h($id) ?>">Pokaż hasło</button>
    </div>
    <?php
}

<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/recovery.php';
$user = adminOnly();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyPost();
    $adminAction = post('action');
    if (!in_array($adminAction, ['create_invite', 'issue_recovery'], true)) {
        http_response_code(400);
        exit('Nieznana operacja administratora.');
    }
    try {
        if ($adminAction === 'create_invite') {
            $plain = bin2hex(random_bytes(32));
            changeRows('INSERT INTO admin_invites (token_hash, created_by, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))',
                [hash('sha256', $plain), (int) $user['id']]);
            $_SESSION['gz_admin_invite_once'] = $plain;
            flash('Zaproszenie utworzone. Skopiuj kod poniżej i przekaż go zaufanej osobie.');
        } else {
            $name = trim(post('recovery_username'));
            $target = getRow('SELECT id, role FROM users WHERE username = ? LIMIT 1', [$name]);
            if (!$target || $target['role'] !== 'user') throw new DomainException('Nie znaleziono zwykłego konta o takiej nazwie.');
            $_SESSION['gz_admin_recovery_once'] = [
                'username' => $name,
                'code' => issueRecoveryCode((int) $target['id']),
            ];
            flash('Kod odzyskiwania wygenerowany. Przekaż go wyłącznie zweryfikowanemu właścicielowi konta.');
        }
    } catch (DomainException $error) {
        flash($error->getMessage(), true);
    } catch (Throwable $error) {
        error_log('GameZone admin invite/recovery: ' . get_class($error));
        flash('Nie udało się wygenerować kodu. Sprawdź migrację SQL.', true);
    }
    redirect($adminAction === 'issue_recovery' ? 'admin.php#admin-recovery' : 'admin.php');
}
$once = $_SESSION['gz_admin_invite_once'] ?? null;
unset($_SESSION['gz_admin_invite_once']);
$adminRecoveryOnce = $_SESSION['gz_admin_recovery_once'] ?? null;
unset($_SESSION['gz_admin_recovery_once']);
// Osobne listy widoczne wyłącznie po potwierdzeniu roli admina na serwerze.
$newsAvailable = true;
$commentsAvailable = true;
$adminNews = [];
$adminComments = [];
$commentsPage = max(1, min(10000, (int) ($_GET['comments_page'] ?? 1)));
$commentsPerPage = 20;
$commentsCount = 0;
try {
    $adminNews = getRows('SELECT id, title, category, published_at FROM news_articles ORDER BY id DESC LIMIT 100');
} catch (PDOException $error) {
    error_log('GameZone admin news list: ' . $error->getCode());
    $newsAvailable = false;
}
try {
    $commentsCount = (int) (getRow('SELECT COUNT(*) AS total FROM review_comments')['total'] ?? 0);
    $pageCount = max(1, (int) ceil($commentsCount / $commentsPerPage));
    $commentsPage = min($commentsPage, $pageCount);
    $offset = ($commentsPage - 1) * $commentsPerPage;
    $adminComments = getRows(
        'SELECT c.id, c.review_id, c.user_id, c.content, c.created_at, u.username, u.avatar_path, g.name AS game_name
         FROM review_comments c
         JOIN users u ON u.id = c.user_id
         JOIN reviews r ON r.id = c.review_id
         JOIN games g ON g.id = r.game_id
         ORDER BY c.created_at DESC, c.id DESC LIMIT ' . (int) $commentsPerPage . ' OFFSET ' . (int) $offset
    );
} catch (PDOException $error) {
    error_log('GameZone admin comments list: ' . $error->getCode());
    $commentsAvailable = false;
}
$counts = [
    'users' => (int) (getRow('SELECT COUNT(*) AS total FROM users WHERE role = ?', ['user'])['total'] ?? 0),
    'admins' => (int) (getRow('SELECT COUNT(*) AS total FROM users WHERE role = ?', ['admin'])['total'] ?? 0),
];
try {
    $inviteCounts = getRow('SELECT COUNT(*) AS total, SUM(used_at IS NULL AND expires_at > NOW()) AS active FROM admin_invites');
} catch (PDOException $error) {
    $inviteCounts = null;
}
pageStart('Panel administratora', 'admin', $user);
?>
<main class="admin-page">
    <header class="admin-hero"><span class="category">🛡️ GAMEZONE</span><h1>Panel administratora</h1><p class="muted">Witaj, <?= h($user['username']) ?>. Możesz publikować aktualności, moderować komentarze oraz zarządzać zaproszeniami.</p></header>
    <?php showFlash(); ?>
    <div class="admin-stats">
        <div class="panel"><strong><?= h($counts['users']) ?></strong><span>Zwykli użytkownicy</span></div>
        <div class="panel"><strong><?= h($counts['admins']) ?></strong><span>Administratorzy</span></div>
        <div class="panel"><strong><?= $inviteCounts ? h($inviteCounts['active'] ?? 0) : '—' ?></strong><span>Aktywne zaproszenia</span></div>
    </div>
    <section class="panel admin-invite">
        <h2>Zaproś nowego administratora</h2>
        <p class="muted">Kod jest losowy, jednorazowy i wygasa po 24 godzinach. W bazie zapisujemy tylko jego skrót SHA-256.</p>
        <?php if (is_string($once) && preg_match('/\A[a-f0-9]{64}\z/', $once)): ?>
            <label for="newInvite">Nowy kod (skopiuj teraz — nie pokażemy go ponownie):</label>
            <textarea readonly id="newInvite" rows="3" spellcheck="false"><?= h($once) ?></textarea>
            <p class="muted">Nowy administrator powinien wejść w <a href="register-admin.php">Rejestracja administratora</a> i wpisać ten kod.</p>
        <?php endif; ?>
        <form method="post" action="admin.php">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="create_invite">
            <button class="primary" type="submit">Wygeneruj kod zaproszenia</button>
        </form>
        <p class="muted">Pierwszy administrator powstaje przez lokalne uruchomienie skryptu <code>php/setup-first-admin.php</code> (instrukcja w README).</p>
    </section>
    <section class="panel admin-invite" id="admin-recovery">
        <h2>🔑 Pomoc w odzyskiwaniu konta</h2>
        <p class="muted">Wygeneruj jednorazowy kod tylko po sprawdzeniu, czy prosząca osoba jest właścicielem konta. Administrator nie może tą drogą resetować innych kont administracyjnych.</p>
        <?php if (is_array($adminRecoveryOnce) && isset($adminRecoveryOnce['username'], $adminRecoveryOnce['code'])): ?>
            <p class="notice">Kod dla użytkownika <strong><?= h($adminRecoveryOnce['username']) ?></strong> (widoczny tylko raz):</p>
            <input type="text" readonly value="<?= h($adminRecoveryOnce['code']) ?>" onclick="this.select()" spellcheck="false">
        <?php endif; ?>
        <form class="stack" method="post" action="admin.php">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="issue_recovery">
            <label for="adminRecoveryUsername">Dokładna nazwa użytkownika</label>
            <input id="adminRecoveryUsername" name="recovery_username" maxlength="50" required>
            <button class="primary" type="submit">Wygeneruj kod dla użytkownika</button>
        </form>
    </section>
    <section class="panel admin-section" id="admin-news">
        <h2>📰 Zarządzanie aktualnościami</h2>
        <?php if (!$newsAvailable): ?>
        <p class="notice error">Brak tabeli <code>news_articles</code>. Zaimportuj <code>sql/aktualnosci_migracja.sql</code> do istniejącej bazy.</p>
        <?php else: ?>
        <p class="muted">Nowa wiadomość pojawi się od razu na stronie <a href="news.php">Aktualności</a>.</p>
        <form method="post" action="admin-actions.php" class="admin-news-form stack">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="create_news">
            <label for="adminNewsTitle">Tytuł (5–180 znaków)</label>
            <input id="adminNewsTitle" name="title" type="text" minlength="5" maxlength="180" required>
            <label for="adminNewsCategory">Kategoria</label>
            <select id="adminNewsCategory" name="category" required>
                <option value="Premiery">Premiery</option>
                <option value="Aktualizacje">Aktualizacje</option>
                <option value="Zapowiedzi">Zapowiedzi</option>
                <option value="E-sport">E-sport</option>
                <option value="Pozostałe">Pozostałe</option>
            </select>
            <label for="adminNewsSummary">Krótki opis (10–500 znaków)</label>
            <textarea id="adminNewsSummary" name="summary" rows="3" minlength="10" maxlength="500" required></textarea>
            <label for="adminNewsBody">Treść artykułu (20–60 000 znaków)</label>
            <textarea id="adminNewsBody" name="content" rows="8" minlength="20" maxlength="60000" required></textarea>
            <button class="primary" type="submit">Opublikuj aktualność</button>
        </form>
        <h3>Opublikowane i zapisane wpisy</h3>
        <?php if (!$adminNews): ?><p class="muted">Brak artykułów. Dodaj pierwszy wpis powyżej.</p><?php endif; ?>
        <div class="admin-moderation-list">
            <?php foreach ($adminNews as $item): ?>
            <article class="admin-moderation-item">
                <div class="admin-moderation-content">
                    <strong><a href="news.php?id=<?= h($item['id']) ?>"><?= h($item['title']) ?></a></strong>
                    <small><?= h($item['category']) ?> · <?= $item['published_at'] ? h(displayDate($item['published_at'])) : 'Szkic' ?></small>
                </div>
                <form method="post" action="admin-actions.php" data-confirm="Na pewno usunąć tę aktualność? Tego nie można cofnąć.">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="delete_news">
                    <input type="hidden" name="news_id" value="<?= h($item['id']) ?>">
                    <button class="danger" type="submit">Usuń aktualność</button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </section>

    <section class="panel admin-section" id="admin-comments">
        <h2>💬 Moderacja komentarzy</h2>
        <?php if (!$commentsAvailable): ?>
        <p class="notice error">Brak tabeli <code>review_comments</code>. Wykonaj migrację rozbudowanych recenzji.</p>
        <?php else: ?>
        <p class="muted">Komentarze w bazie: <strong><?= h($commentsCount) ?></strong>. Każdy komentarz możesz usunąć, niezależnie od autora. Możesz też usuwać je na stronie recenzji.</p>
        <?php if (!$adminComments): ?><p class="muted">Brak komentarzy do moderacji.</p><?php endif; ?>
        <div class="admin-moderation-list">
            <?php foreach ($adminComments as $comment): ?>
            <article class="admin-moderation-item" id="admin-comment-<?= h($comment['id']) ?>">
                <div class="admin-moderation-content">
                    <div class="author-id">
                        <span class="avatar avatar-small" aria-hidden="true"><?php avatarMarkup([
                            'id' => $comment['user_id'],
                            'avatar_path' => $comment['avatar_path'],
                        ]); ?></span>
                        <strong><?= h($comment['username']) ?></strong>
                    </div>
                    <small><?= h($comment['game_name']) ?> · <?= h(displayDate($comment['created_at'])) ?></small>
                    <p><?= h($comment['content']) ?></p>
                    <a href="review.php?id=<?= h($comment['review_id']) ?>#comment-<?= h($comment['id']) ?>">Otwórz komentarz w recenzji</a>
                </div>
                <form method="post" action="admin-actions.php" data-confirm="Na pewno usunąć komentarz tego użytkownika?">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="comment_id" value="<?= h($comment['id']) ?>">
                    <input type="hidden" name="comments_page" value="<?= h($commentsPage) ?>">
                    <button class="danger" type="submit">Usuń komentarz</button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if ($commentsCount > $commentsPerPage): ?>
        <nav class="admin-pagination" aria-label="Strony komentarzy">
            <?php if ($commentsPage > 1): ?><a class="secondary" href="admin.php?comments_page=<?= h($commentsPage - 1) ?>#admin-comments">← Poprzednie</a><?php endif; ?>
            <span>Strona <?= h($commentsPage) ?> z <?= h(max(1, (int) ceil($commentsCount / $commentsPerPage))) ?></span>
            <?php if ($commentsPage * $commentsPerPage < $commentsCount): ?><a class="secondary" href="admin.php?comments_page=<?= h($commentsPage + 1) ?>#admin-comments">Następne →</a><?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="panel admin-invite"><h2>Administracja reklamami</h2><p>Dodawaj nowe reklamy JPG, PNG, WebP i GIF (do 5 MB) oraz zmieniaj zdjęcia już istniejących. Aktywne reklamy są losowane w stopce.</p><a class="secondary" href="footer-ad-admin.php">Dodaj reklamę / Zarządzaj reklamami</a></section>
</main>
<?php pageEnd(); ?>

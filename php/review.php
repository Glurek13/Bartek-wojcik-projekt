<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/reviews.php';

$user = signedInUser();
$viewer = (int) $user['id'];
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$from = ($_GET['from'] ?? '') === 'profile' ? 'profile' : 'home';
$back = $from === 'profile' ? 'profile.php#my-reviews' : 'index.php#reviews';
$review = $id ? (fetchReviews($viewer, null, $id)[0] ?? null) : null;
if (!$review) {
    http_response_code(404);
    pageStart('Nie znaleziono recenzji', 'review', $user);
    echo '<main class="review-detail"><h1>Nie znaleziono recenzji</h1><a href="' . h($back) . '">Wróć do recenzji</a></main>';
    pageEnd();
    exit;
}
$isOwner = $review['user_id'] !== null && (int) $review['user_id'] === $viewer;
$editing = $isOwner && isset($_GET['edit']);
$deleting = $isOwner && isset($_GET['delete']);
$interactions = reviewInteractionsAvailable();
$comments = $interactions ? getRows('SELECT c.id, c.user_id, c.content, c.created_at, u.username
                     FROM review_comments AS c JOIN users AS u ON u.id = c.user_id
                     WHERE c.review_id = ? ORDER BY c.created_at, c.id', [$id]) : [];
$myCommentId = null;
foreach ($comments as $comment) {
    if ((int) $comment['user_id'] === $viewer) {
        $myCommentId = (int) $comment['id'];
        break;
    }
}
pageStart('Recenzja: ' . $review['game_name'], 'review', $user);
?>
<main class="review-detail">
    <a class="review-back" href="<?= h($back) ?>">← <?= $from === 'profile' ? 'Moje recenzje' : 'Najnowsze recenzje' ?></a>
    <h1>Recenzja gry</h1>
    <?php if (($_GET['status'] ?? '') === 'review') showFlash(); ?>
    <?php reviewCard($review, $viewer, $from, true); ?>

    <?php if ($editing): ?>
    <section class="panel" id="edit-review">
        <h2>Edytuj recenzję</h2>
        <?php if (($_GET['status'] ?? '') === 'edit') showFlash(); ?>
        <form method="post" action="review-actions.php" class="stack">
            <?php reviewActionFields('edit', $id, $from); ?>
            <label for="editRating">Ocena (1–10)</label>
            <input id="editRating" name="rating" type="number" min="1" max="10" step="0.1" required value="<?= h($review['rating']) ?>">
            <label for="editContent">Twoja opinia</label>
            <textarea id="editContent" name="content" rows="5" minlength="5" maxlength="3000" required><?= h($review['content']) ?></textarea>
            <button class="primary" type="submit">Zapisz zmiany</button>
            <a href="<?= h(reviewPageUrl($id, $from)) ?>">Anuluj edycję</a>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($deleting): ?>
    <section class="panel" id="delete-review">
        <h2>Usunąć tę recenzję?</h2>
        <p class="muted">Znikną też jej komentarze i polubienia.</p>
        <form method="post" action="review-actions.php" class="review-owner-actions">
            <?php reviewActionFields('delete', $id, $from); ?>
            <button class="danger" type="submit">Usuń recenzję</button>
            <a href="<?= h(reviewPageUrl($id, $from)) ?>">Anuluj</a>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($interactions): ?>
    <section class="panel" id="comments">
        <h2>Komentarze (<?= count($comments) ?>)</h2>
        <?php if (($_GET['status'] ?? '') === 'comments') showFlash(); ?>
        <?php if (!$comments): ?><p class="muted">Nie ma jeszcze komentarzy.</p><?php endif; ?>
        <ul class="review-comments">
            <?php foreach ($comments as $comment): ?>
            <li id="comment-<?= h($comment['id']) ?>">
                <div class="comment-heading">
                    <strong><?= h($comment['username']) ?></strong>
                    <small><?= h(displayDate($comment['created_at'])) ?></small>
                </div>
                <p><?= h($comment['content']) ?></p>
                <?php if ((int) $comment['user_id'] === $viewer): ?>
                <form method="post" action="review-actions.php">
                    <?php reviewActionFields('delete-comment', $id, $from); ?>
                    <input type="hidden" name="comment_id" value="<?= h($comment['id']) ?>">
                    <button class="review-button" type="submit">Usuń swój komentarz</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($myCommentId === null): ?>
        <form method="post" action="review-actions.php" class="stack">
            <?php reviewActionFields('comment', $id, $from); ?>
            <label for="commentContent">Twój komentarz</label>
            <p class="muted">Możesz dodać jeden komentarz pod tą recenzją.</p>
            <textarea id="commentContent" name="content" rows="3" maxlength="1000" required></textarea>
            <button type="submit" class="primary">Dodaj komentarz</button>
        </form>
        <?php else: ?>
        <p class="muted">Masz już <a href="#comment-<?= h($myCommentId) ?>">swój komentarz</a> pod tą recenzją. Aby dodać inny, usuń poprzedni.</p>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section id="comments"><?php reviewSetupNotice(); ?></section>
    <?php endif; ?>
</main>
<?php pageEnd(); ?>

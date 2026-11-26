<?php
declare(strict_types=1);

function reviewInteractionsAvailable(): bool
{
    static $available = null;
    if ($available === null) {
        $tables = getRow("SELECT COUNT(*) AS total FROM information_schema.TABLES
                          WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME IN ('review_likes', 'review_comments')");
        $available = (int) ($tables['total'] ?? 0) === 2;
    }
    return $available;
}

function fetchReviews(int $viewer, ?int $author = null, ?int $reviewId = null): array
{
    $interactions = reviewInteractionsAvailable();
    $counts = $interactions
        ? '(SELECT COUNT(*) FROM review_likes AS l WHERE l.review_id = r.id) AS like_count,
           (SELECT COUNT(*) FROM review_comments AS c WHERE c.review_id = r.id) AS comment_count,
           (mine.user_id IS NOT NULL) AS liked'
        : '0 AS like_count, 0 AS comment_count, 0 AS liked';
    $query = 'SELECT r.id, r.game_id, r.user_id, r.author, r.rating, r.content, r.created_at,
                     COALESCE(author_user.username, r.author) AS display_author,
                     author_user.avatar_path AS author_avatar_path,
                     g.name AS game_name, ' . $counts . '
              FROM reviews AS r
              JOIN games AS g ON g.id = r.game_id
              LEFT JOIN users AS author_user ON author_user.id = r.user_id';
    $where = [];
    $params = [];
    if ($interactions) {
        $query .= ' LEFT JOIN review_likes AS mine ON mine.review_id = r.id AND mine.user_id = ?';
        $params[] = $viewer;
    }
    if ($author !== null) {
        $where[] = 'r.user_id = ?';
        $params[] = $author;
    }
    if ($reviewId !== null) {
        $where[] = 'r.id = ?';
        $params[] = $reviewId;
    }
    if ($where) $query .= ' WHERE ' . implode(' AND ', $where);
    return getRows($query . ' ORDER BY r.created_at DESC, r.id DESC', $params);
}

function reviewPageUrl(int $id, string $from, array $params = [], string $fragment = ''): string
{
    return 'review.php?' . http_build_query(['id' => $id] + ($from === 'profile' ? ['from' => 'profile'] : []) + $params) . $fragment;
}

function reviewActionFields(string $action, int $id, string $from): void
{
    csrfField();
    ?>
    <input type="hidden" name="action" value="<?= h($action) ?>">
    <input type="hidden" name="review_id" value="<?= h($id) ?>">
    <input type="hidden" name="from" value="<?= h($from) ?>">
    <?php
}

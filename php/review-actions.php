<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/reviews.php';

verifyPost();
$user = signedInUser();
$viewer = (int) $user['id'];
$id = filter_var(post('review_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = post('action');
$likeJson = $action === 'like' && wantsJson();
$from = post('from') === 'profile' ? 'profile' : 'home';
$list = $from === 'profile' ? 'profile.php?review=1#my-reviews' : 'index.php?review=1#reviews';
if (!$id || !in_array($action, ['like', 'edit', 'delete', 'comment', 'delete-comment'], true)) {
    if ($likeJson) jsonReply(['error' => 'Nieprawidłowa recenzja.'], 400);
    http_response_code(400);
    exit('Nieprawidłowa operacja na recenzji.');
}
$back = reviewPageUrl($id, $from, ['status' => 'review']);
if ($action === 'like' && post('view') === 'list') {
    $back = $from === 'profile' ? 'profile.php#review-' . $id : 'index.php#review-' . $id;
}
if ($action === 'comment' || $action === 'delete-comment') {
    $back = reviewPageUrl($id, $from, ['status' => 'comments'], '#comments');
}
if ($action === 'edit') {
    $back = reviewPageUrl($id, $from, ['edit' => 1, 'status' => 'edit'], '#edit-review');
}

try {
    if (in_array($action, ['like', 'comment', 'delete-comment'], true) && !reviewInteractionsAvailable()) {
        throw new DomainException('Zaimportuj rozbudowane_recenzje_migracja.sql, aby włączyć polubienia i komentarze.');
    }
    db()->beginTransaction();
    $review = getRow('SELECT user_id FROM reviews WHERE id = ? FOR UPDATE', [$id]);
    if (!$review) {
        $back = $list;
        throw new DomainException('Ta recenzja już nie istnieje.');
    }
    if (in_array($action, ['edit', 'delete'], true) && ($review['user_id'] === null || (int) $review['user_id'] !== $viewer)) {
        rollbackIfNeeded();
        http_response_code(403);
        exit('Możesz zmieniać tylko własne recenzje.');
    }
    switch ($action) {
        case 'like':
            $liked = post('liked');
            if (!in_array($liked, ['0', '1'], true)) throw new DomainException('Nieprawidłowe działanie polubienia.');
            if ($liked === '1') {
                changeRows('INSERT INTO review_likes (review_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE review_id = review_id', [$id, $viewer]);
            } else {
                changeRows('DELETE FROM review_likes WHERE review_id = ? AND user_id = ?', [$id, $viewer]);
            }
            if ($likeJson) {
                $count = getRow('SELECT COUNT(*) AS total FROM review_likes WHERE review_id = ?', [$id]);
                $likeResult = [
                    'liked' => $liked === '1',
                    'count' => (int) $count['total'],
                    'message' => $liked === '1' ? 'Polubiono recenzję.' : 'Cofnięto polubienie.',
                ];
            }
            break;
        case 'edit':
            $rating = str_replace(',', '.', trim(post('rating')));
            $content = trim(post('content'));
            $length = textLength($content);
            if (!preg_match('/\A(?:[1-9](?:\.[0-9])?|10(?:\.0)?)\z/', $rating) || $length < 5 || $length > 3000) {
                throw new DomainException('Podaj ocenę od 1 do 10 i treść od 5 do 3000 znaków.');
            }
            changeRows('UPDATE reviews SET rating = ?, content = ? WHERE id = ? AND user_id = ?', [$rating, $content, $id, $viewer]);
            // Inline editor: return to the same review card; the detail editor keeps its previous behavior.
            $back = post('view') === 'list'
                ? ($from === 'profile' ? 'profile.php?review=1#review-' . $id : 'index.php?review=1#review-' . $id)
                : reviewPageUrl($id, $from, ['status' => 'review']);
            break;
        case 'delete':
            changeRows('DELETE FROM reviews WHERE id = ? AND user_id = ?', [$id, $viewer]);
            $back = $list;
            break;
        case 'comment':
            $content = trim(post('content'));
            $length = textLength($content);
            if ($length < 1 || $length > 1000) throw new DomainException('Komentarz musi mieć od 1 do 1000 znaków.');
            // The review row was locked above, before checking and inserting.
            if (getRow('SELECT id FROM review_comments WHERE review_id = ? AND user_id = ? LIMIT 1 FOR UPDATE', [$id, $viewer])) {
                throw new DomainException('Masz już komentarz pod tą recenzją.');
            }
            changeRows('INSERT INTO review_comments (review_id, user_id, content) VALUES (?, ?, ?)', [$id, $viewer, $content]);
            break;
        case 'delete-comment':
            $commentId = filter_var(post('comment_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $comment = $commentId ? getRow('SELECT user_id FROM review_comments WHERE id = ? AND review_id = ?', [$commentId, $id]) : null;
            if (!$comment) throw new DomainException('Komentarz już nie istnieje.');
            if ((int) $comment['user_id'] !== $viewer) {
                rollbackIfNeeded();
                http_response_code(403);
                exit('Możesz usuwać tylko własne komentarze.');
            }
            changeRows('DELETE FROM review_comments WHERE id = ? AND review_id = ? AND user_id = ?', [$commentId, $id, $viewer]);
            break;
    }
    db()->commit();
    if (isset($likeResult)) jsonReply($likeResult);
    $messages = ['edit' => 'Zapisano zmiany w recenzji.', 'delete' => 'Usunięto recenzję.', 'comment' => 'Dodano komentarz.', 'delete-comment' => 'Usunięto komentarz.'];
    if (isset($messages[$action])) flash($messages[$action]);
} catch (DomainException $error) {
    rollbackIfNeeded();
    if ($likeJson) jsonReply(['error' => $error->getMessage()], 422);
    flash($error->getMessage(), true);
    if ($action === 'like' && $back !== $list) $back = reviewPageUrl($id, $from, ['status' => 'review']);
} catch (PDOException $error) {
    rollbackIfNeeded();
    error_log('GameZone review operation: ' . $error->getCode());
    if ($likeJson) jsonReply(['error' => 'Nie udało się zapisać polubienia. Spróbuj ponownie.'], 500);
    flash('Nie udało się zapisać zmian. Spróbuj ponownie.', true);
    if ($action === 'like') $back = reviewPageUrl($id, $from, ['status' => 'review']);
}
redirect($back);

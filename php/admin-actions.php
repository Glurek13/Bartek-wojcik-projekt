<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Kontrola roli jest po stronie serwera, nie wystarczy ukryty przycisk w HTML.
$admin = adminOnly();
verifyPost();
$action = post('action');
if (!in_array($action, ['create_news', 'delete_news', 'delete_comment'], true)) {
    http_response_code(400);
    exit('Nieznana operacja administratora.');
}
$returnTo = $action === 'delete_comment' ? 'admin.php#admin-comments' : 'admin.php#admin-news';
if ($action === 'delete_comment' && post('origin') === 'review') {
    $returnTo = 'admin.php#admin-comments'; // Nadpisujemy tylko po potwierdzeniu powiązania z recenzją.
} elseif ($action === 'delete_comment') {
    $page = filter_var(post('comments_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($page && $page <= 10000) $returnTo = 'admin.php?comments_page=' . $page . '#admin-comments';
}
try {
    switch ($action) {
        case 'create_news':
            $title = trim(post('title'));
            $category = trim(post('category'));
            $summary = trim(post('summary'));
            $content = trim(post('content'));
            if (textLength($title) < 5 || textLength($title) > 180 ||
                !in_array($category, ['Premiery', 'Aktualizacje', 'Zapowiedzi', 'E-sport', 'Pozostałe'], true) ||
                textLength($summary) < 10 || textLength($summary) > 500 ||
                textLength($content) < 20 || textLength($content) > 60000) {
                throw new DomainException('Sprawdź długość tytułu, opisu, treści i kategorię artykułu.');
            }
            $slug = 'wpis-' . bin2hex(random_bytes(16));
            changeRows('INSERT INTO news_articles (slug, title, summary, content, category, published_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
                [$slug, $title, $summary, $content, $category]);
            flash('Opublikowano aktualność: ' . $title);
            break;
        case 'delete_news':
            $id = filter_var(post('news_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) throw new DomainException('Nieprawidłowy numer artykułu.');
            $statement = db()->prepare('DELETE FROM news_articles WHERE id = ?');
            $statement->execute([$id]);
            flash($statement->rowCount() > 0 ? 'Aktualność została usunięta.' : 'Aktualność już nie istnieje.');
            break;
        case 'delete_comment':
            $id = filter_var(post('comment_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) throw new DomainException('Nieprawidłowy numer komentarza.');
            $row = getRow('SELECT review_id FROM review_comments WHERE id = ?', [$id]);
            if (!$row) throw new DomainException('Komentarz już nie istnieje.');
            if (post('origin') === 'review') {
                $returnTo = 'review.php?id=' . (int) $row['review_id'] . '&status=comments#comments';
            }
            changeRows('DELETE FROM review_comments WHERE id = ?', [$id]);
            flash('Usunięto komentarz użytkownika.');
            break;
    }
} catch (DomainException $error) {
    flash($error->getMessage(), true);
} catch (PDOException $error) {
    error_log('GameZone admin content action SQL error: ' . $error->getCode());
    flash('Nie udało się zapisać zmian. Sprawdź, czy tabele aktualności i komentarzy istnieją.', true);
} catch (Throwable $error) {
    error_log('GameZone admin content action error: ' . get_class($error));
    flash('Nie udało się wykonać operacji. Spróbuj ponownie.', true);
}
redirect($returnTo);

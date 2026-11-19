<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';

$user = signedInUser();
$requestId = $_GET['id'] ?? null;
$slug = $_GET['artykul'] ?? null;
$isDetail = $requestId !== null || $slug !== null;
$validId = is_string($requestId) && preg_match('/\A[1-9][0-9]{0,9}\z/', $requestId) === 1;
// Older links can use spaces and Polish letters in the saved slug.
$validSlug = is_string($slug) && strlen($slug) <= 480
    && preg_match('/\A[^\p{C}]{1,120}\z/u', $slug) === 1;
$article = null;
$articles = [];
$available = true;
$published = 'published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP';

try {
    if ($validId) {
        $article = getRow(
            'SELECT title, summary, content, category, published_at FROM news_articles WHERE id = ? AND ' . $published,
            [$requestId]
        );
    } elseif ($requestId === null && $validSlug) {
        $article = getRow(
            'SELECT title, summary, content, category, published_at FROM news_articles WHERE slug = ? AND ' . $published,
            [$slug]
        );
    } elseif (!$isDetail) {
        $articles = getRows(
            'SELECT id, title, summary, category, published_at FROM news_articles WHERE ' . $published .
            ' ORDER BY published_at DESC, id DESC'
        );
    }
} catch (PDOException $error) {
    if ((string) $error->getCode() !== '42S02') throw $error;
    $available = false;
}

if (!$available) {
    http_response_code(503);
} elseif ($isDetail && !$article) {
    http_response_code(404);
}

pageStart($article['title'] ?? 'Aktualności gamingowe', 'news', $user);
?>
<main class="news-page">
    <?php if ($isDetail): ?>
    <a class="news-back" href="news.php">← Wszystkie aktualności</a>
    <?php endif; ?>

    <?php if (!$available): ?>
    <h1>Aktualności gamingowe</h1>
    <p class="notice" role="status">Ta sekcja nie jest jeszcze skonfigurowana. W phpMyAdmin wybierz bazę GameZone i zaimportuj <strong>aktualnosci_migracja.sql</strong>.</p>
    <?php elseif ($isDetail && !$article): ?>
    <h1>Nie znaleziono artykułu</h1>
    <p class="muted">Wpis nie istnieje albo nie został jeszcze opublikowany.</p>
    <?php elseif ($article): ?>
    <article class="panel news-article">
        <div class="news-meta">
            <span class="news-tag"><?= h($article['category']) ?></span>
            <time datetime="<?= h(substr($article['published_at'], 0, 10)) ?>"><?= h(displayDate($article['published_at'])) ?></time>
        </div>
        <h1><?= h($article['title']) ?></h1>
        <p class="news-summary"><?= h($article['summary']) ?></p>
        <div class="news-body"><?= h($article['content']) ?></div>
    </article>
    <?php else: ?>
    <div class="news-intro">
        <p class="category">GameZone</p>
        <h1>Aktualności gamingowe</h1>
        <p class="muted">Premiery, aktualizacje i zapowiedzi ze świata gier.</p>
    </div>
    <?php if (!$articles): ?>
    <p class="panel muted">Nie ma jeszcze opublikowanych aktualności.</p>
    <?php endif; ?>
    <div class="news-grid">
        <?php foreach ($articles as $item): ?>
        <article class="panel news-card">
            <div class="news-meta">
                <span class="news-tag"><?= h($item['category']) ?></span>
                <time datetime="<?= h(substr($item['published_at'], 0, 10)) ?>"><?= h(displayDate($item['published_at'])) ?></time>
            </div>
            <h2><a href="<?= h('news.php?id=' . $item['id']) ?>"><?= h($item['title']) ?></a></h2>
            <p><?= h($item['summary']) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php pageEnd(); ?>

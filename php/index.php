<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/reviews.php';

$user = signedInUser();
$categories = getRows('SELECT id, name, icon FROM categories ORDER BY id');
$games = getRows('SELECT g.id, g.name, g.description, g.image, g.rating, g.created_at, c.name AS category, g.category_id, f.game_id IS NOT NULL AS favorite FROM games g JOIN categories c ON c.id = g.category_id LEFT JOIN favorites f ON f.game_id = g.id AND f.user_id = ? ORDER BY g.created_at DESC, g.id DESC', [$user['id']]);
$favorites = array_filter($games, fn ($game) => $game['favorite']);
$viewer = (int) $user['id'];
$reviews = fetchReviews($viewer);
$reviewedGames = [];
foreach ($reviews as $review) {
    if ($review['user_id'] !== null && (int) $review['user_id'] === $viewer) {
        $reviewedGames[(int) $review['game_id']] = true;
    }
}
$reviewableGames = array_filter($games, fn ($game) => !isset($reviewedGames[(int) $game['id']]));

function gameCard(array $game, string $section): void
{
    $image = gameImage($game['image']);
    $favorite = (bool) $game['favorite'];
    ?>
    <article class="game-card" data-game data-title="<?= h($game['name']) ?>" data-category="<?= h($game['category_id']) ?>" data-rating="<?= h($game['rating'] ?? 0) ?>" data-created="<?= h($game['created_at']) ?>" data-id="<?= h($game['id']) ?>" data-favorite="<?= $favorite ? '1' : '0' ?>">
        <?php if ($image): ?><img class="game-cover" loading="lazy" src="<?= h($image) ?>" alt=""><?php else: ?><div class="game-cover cover-placeholder" aria-hidden="true"><img src="../assets/logo.svg" width="64" height="64" alt=""></div><?php endif; ?>
        <div class="game-info">
            <div class="game-top">
                <span class="category"><?= h($game['category']) ?></span>
                <form method="post" action="actions.php" data-live-action="favorite">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="favorite">
                    <input type="hidden" name="game_id" value="<?= h($game['id']) ?>">
                    <input type="hidden" name="favorite" value="<?= $favorite ? '0' : '1' ?>">
                    <input type="hidden" name="section" value="<?= h($section) ?>">
                    <button class="heart<?= $favorite ? ' active' : '' ?>" type="submit" aria-label="<?= h(($favorite ? 'Usuń z ulubionych' : 'Dodaj do ulubionych') . ' — ' . $game['name']) ?>" aria-pressed="<?= $favorite ? 'true' : 'false' ?>"><?= $favorite ? '♥' : '♡' ?></button>
                </form>
            </div>
            <h3><?= h($game['name']) ?></h3>
            <p><?= h($game['description']) ?></p>
            <strong class="rating">⭐ <?= h(formatRating((float) $game['rating'])) ?></strong>
        </div>
    </article>
    <?php
}

pageStart('Świat gier', 'home', $user);
?>
<main>
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Świat gier w jednym miejscu</h1>
            <p>Odkrywaj gry, sprawdzaj oceny, czytaj recenzje i znajdź swój kolejny ulubiony tytuł.</p>
            <a class="primary" href="#games">Zobacz gry</a>
        </div>
    </section>

    <section class="section categories" aria-labelledby="categories-title">
        <h2 id="categories-title">Kategorie</h2>
        <div id="categoryList" class="category-list">
            <button type="button" data-category-filter="" aria-pressed="true">Wszystkie</button>
            <?php foreach ($categories as $category): ?>
            <button type="button" data-category-filter="<?= h($category['id']) ?>" aria-pressed="false"><?= h(trim(($category['icon'] ?? '') . ' ' . $category['name'])) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section" id="games">
        <h2>Popularne gry</h2>
        <?php if (isset($_GET['favorite'])) showFlash(); ?>
        <div class="filters">
            <label for="minRating">Minimalna ocena
                <select id="minRating">
                    <option value="0">Wszystkie oceny</option><option value="7">7/10 i wyżej</option><option value="8">8/10 i wyżej</option><option value="9">9/10 i wyżej</option>
                </select>
            </label>
            <label for="sortGames">Sortowanie
                <select id="sortGames">
                    <option value="newest">Najnowsze w katalogu</option><option value="rating-desc">Najwyżej oceniane</option><option value="rating-asc">Najniżej oceniane</option><option value="name-asc">Tytuł A–Z</option><option value="name-desc">Tytuł Z–A</option>
                </select>
            </label>
            <label class="checkbox"><input id="onlyFavorites" type="checkbox"> Tylko ulubione</label>
            <button type="button" id="clearFilters" class="secondary">Wyczyść filtry</button>
        </div>
        <div id="gamesContainer" class="games-grid">
            <?php foreach ($games as $game) gameCard($game, 'games'); ?>
        </div>
        <p id="noResults" class="empty" role="status"<?= $games ? ' hidden' : '' ?>>Nie znaleziono żadnej gry.</p>
    </section>

    <section class="section alt" id="favorites">
        <h2 id="favoriteHeading" tabindex="-1">♥ Moje ulubione gry</h2>
        <?php if (isset($_GET['unfavorite'])) showFlash(); ?>
        <p class="empty" id="favoritesEmpty"<?= $favorites ? ' hidden' : '' ?>>Kliknij ♡ na karcie gry, aby dodać ją do ulubionych.</p>
        <div class="games-grid" id="favoritesContainer"><?php foreach ($favorites as $game) gameCard($game, 'favorites'); ?></div>
    </section>

    <section class="section" id="reviews">
        <h2>Najnowsze recenzje</h2>
        <?php if (isset($_GET['review'])) showFlash(); ?>
        <?php reviewSetupNotice(); ?>
        <?php if ($reviewableGames): ?>
        <form class="panel review-form" method="post" action="actions.php">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="review">
            <h3>Dodaj własną opinię</h3>
            <p class="muted">Możesz dodać jedną recenzję na grę.</p>
            <?php if ($reviewedGames): ?><p class="muted">Swoje opinie możesz zmienić w sekcji <a href="profile.php#my-reviews">Moje recenzje</a>.</p><?php endif; ?>
            <label for="reviewGame">Gra</label>
            <select name="game_id" id="reviewGame" required>
                <?php foreach ($reviewableGames as $game): ?><option value="<?= h($game['id']) ?>"><?= h($game['name']) ?></option><?php endforeach; ?>
            </select>
            <label for="reviewRating">Ocena (1–10)</label>
            <select name="rating" id="reviewRating" required>
                <?php for ($rating = 10; $rating >= 1; $rating--): ?><option value="<?= $rating ?>"><?= $rating ?></option><?php endfor; ?>
            </select>
            <label for="reviewContent">Twoja opinia</label>
            <textarea name="content" id="reviewContent" rows="4" minlength="5" maxlength="3000" required></textarea>
            <button type="submit" class="primary">Opublikuj opinię</button>
        </form>
        <?php elseif ($games): ?>
        <p class="empty">Wszystkie gry w katalogu mają już Twoją opinię. <a href="profile.php#my-reviews">Zobacz i edytuj swoje recenzje</a>.</p>
        <?php endif; ?>
        <div class="reviews-grid">
            <?php foreach ($reviews as $review) reviewCard($review, $viewer, 'home'); ?>
        </div>
        <?php if (!$reviews): ?><p class="empty">Nie ma jeszcze recenzji.</p><?php endif; ?>
    </section>

    <section class="section alt about" id="about">
        <h2>O GameZone</h2>
        <p>GameZone to miejsce dla miłośników gier. Znajdziesz tutaj informacje o popularnych tytułach, oceny i recenzje społeczności.</p>
    </section>
</main>
<?php pageEnd(); ?>

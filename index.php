<?php
session_start();
if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>GameZone - Świat Gier</title><link rel="stylesheet" href="style.css?v=3"><script src="script.js?v=5" defer></script></head>
<body>
<header><div class="logo">🎮 GameZone</div><nav><a href="#home">Strona główna</a><a href="#games">Gry</a><a href="#reviews">Recenzje</a><a href="logout.php">Wyloguj się</a><a href="#about">O nas</a></nav><form class="search" id="searchForm"><input type="search" id="searchInput" placeholder="Szukaj gry..." aria-label="Szukaj gry"><button type="submit">🔍</button></form></header>
<section class="hero" id="home"><div class="hero-content"><h1>Świat gier w jednym miejscu</h1><p>Odkrywaj gry, sprawdzaj oceny, czytaj recenzje i znajdź swój kolejny ulubiony tytuł.</p><a href="#games" class="hero-button">Zobacz gry</a></div></section>
<section class="categories"><h2>Kategorie</h2><div class="category-list" id="categoryList"><p>Ładowanie kategorii...</p></div></section>
<section class="games-section" id="games"><h2>Popularne gry</h2><p id="statusMessage" class="status-message" role="status">Ładowanie gier...</p><div class="games-container" id="gamesContainer"></div><p id="noResults" class="no-results" hidden>Nie znaleziono żadnej gry.</p></section>
<section class="reviews" id="reviews"><h2>Najnowsze recenzje</h2><div id="reviewFormWrap" class="review-form-wrap" hidden><form id="reviewForm" class="form-panel"><h3>Dodaj własną opinię</h3><label for="reviewGame">Gra</label><select id="reviewGame" name="game_id" required></select><label for="reviewRating">Ocena (1–10)</label><select id="reviewRating" name="rating" required><option value="10">10 — rewelacyjna</option><option value="9">9</option><option value="8">8</option><option value="7">7</option><option value="6">6</option><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1 — bardzo słaba</option></select><label for="reviewContent">Treść opinii</label><textarea id="reviewContent" name="content" rows="5" minlength="5" maxlength="3000" placeholder="Napisz, co sądzisz o tej grze..." required></textarea><button type="submit">Opublikuj opinię</button></form></div><p id="reviewLoginHint" class="review-login-hint">Zaloguj się, aby dodać własną opinię.</p><p id="reviewMessage" class="form-message" role="status"></p><div class="review-container" id="reviewContainer"><p>Ładowanie recenzji...</p></div></section>
<section class="about" id="about"><h2>O GameZone</h2><p>GameZone to miejsce stworzone dla wszystkich miłośników gier. Znajdziesz tutaj informacje o popularnych tytułach, oceny i recenzje społeczności.</p></section><footer><p>© 2026 GameZone | Wszystkie prawa zastrzeżone</p></footer>
</body></html>

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$dbHost = '127.0.0.1';
$dbName = 'gamezone';
$dbUser = 'root';
$dbPassword = ''; // Wpisz hasło MySQL, jeśli je ustawiono.

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $action = $_GET['action'] ?? 'all';

    if ($action === 'games') {
        $stmt = $pdo->query(
            'SELECT g.id, g.name, g.description, g.image, g.rating,
                    c.id AS category_id, c.name AS category, c.icon AS category_icon
             FROM games g
             JOIN categories c ON c.id = g.category_id
             ORDER BY g.created_at DESC, g.id DESC'
        );
        echo json_encode(['games' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif ($action === 'categories') {
        $stmt = $pdo->query('SELECT id, name, icon FROM categories ORDER BY id');
        echo json_encode(['categories' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    } elseif ($action === 'reviews') {
        $stmt = $pdo->query(
            'SELECT r.id, r.author, r.content, r.rating, r.created_at,
                    g.name AS game_name
             FROM reviews r
             JOIN games g ON g.id = r.game_id
             ORDER BY r.created_at DESC, r.id DESC'
        );
        echo json_encode(['reviews' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    } elseif ($action === 'all') {
        $games = $pdo->query(
            'SELECT g.id, g.name, g.description, g.image, g.rating,
                    c.id AS category_id, c.name AS category, c.icon AS category_icon
             FROM games g
             JOIN categories c ON c.id = g.category_id
             ORDER BY g.created_at DESC, g.id DESC'
        )->fetchAll();

        $categories = $pdo->query('SELECT id, name, icon FROM categories ORDER BY id')->fetchAll();

        $reviews = $pdo->query(
            'SELECT r.id, r.author, r.content, r.rating, r.created_at,
                    g.name AS game_name
             FROM reviews r
             JOIN games g ON g.id = r.game_id
             ORDER BY r.created_at DESC, r.id DESC'
        )->fetchAll();

        echo json_encode(
            ['games' => $games, 'categories' => $categories, 'reviews' => $reviews],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Nieznana akcja API.'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    // Szczegóły błędu zapisuj na serwerze, nie pokazuj użytkownikom.
    error_log((string) $e);
    echo json_encode([
        'error' => 'Nie udało się połączyć z bazą danych. Sprawdź ustawienia w api.php i czy baza gamezone została zaimportowana.'
    ], JSON_UNESCAPED_UNICODE);
}

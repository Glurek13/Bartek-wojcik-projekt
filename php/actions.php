<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
verifyPost();
$action = post('action');
$destinations = [
    'login' => 'login.php', 'register' => 'register.php',
    'logout' => 'profile.php', 'rename' => 'profile.php?rename=1#settings',
    'password' => 'profile.php?password=1#password-settings', 'avatar' => 'profile.php?avatar=1#photo',
    'review' => 'index.php?review=1#reviews', 'favorite' => 'index.php?favorite=1#games',
    'unfavorite' => 'profile.php?unfavorite=1#favorite-games',
];
if (!isset($destinations[$action])) {
    http_response_code(400);
    exit('Nieznana akcja.');
}
$returnTo = $destinations[$action];
$favoriteJson = wantsJson() && in_array($action, ['favorite', 'unfavorite'], true);

function matchingPassword(?array $user, string $password): bool
{
    return $user && $user['password'] !== '' && hash_equals($user['password'], $password);
}

function validPassword(string $password): bool
{
    return textLength($password) >= 8 && strlen($password) <= 255;
}

function validUsername(string $name): bool
{
    $length = textLength($name);
    return $length >= 3 && $length <= 50 && !preg_match('/[[:cntrl:]]/u', $name);
}

function saveAvatar(int $id, ?string $previous): void
{
    $file = $_FILES['avatar'] ?? [];
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    $tmp = $file['tmp_name'] ?? '';
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > 5242880) {
        throw new DomainException('Zdjęcie lub GIF nie może przekraczać 5 MB. Jeśli PHP blokuje przesyłanie, sprawdź ustawienia upload_max_filesize i post_max_size.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)) {
        throw new DomainException('Nie udało się przesłać zdjęcia. Wybierz plik i spróbuj ponownie.');
    }
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $size = @getimagesize($tmp);
    if (!isset($allowed[$type]) || !$size || ($size['mime'] ?? '') !== $type || $size[0] < 1 || $size[1] < 1 || max($size[0], $size[1]) > 4000) {
        throw new DomainException('Wybierz JPG, PNG, WEBP lub GIF (również animowany) o wymiarach do 4000 × 4000 px.');
    }
    $folder = dirname(__DIR__) . '/uploads/avatars';
    if (!is_dir($folder) || !is_writable($folder)) {
        throw new DomainException('PHP nie może zapisać zdjęcia w folderze uploads/avatars.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$type];
    $destination = $folder . '/' . $name;
    if (!move_uploaded_file($tmp, $destination)) {
        throw new DomainException('Nie udało się zapisać zdjęcia na dysku.');
    }
    try {
        changeRows('UPDATE users SET avatar_path = ? WHERE id = ?', ['uploads/avatars/' . $name, $id]);
    } catch (Throwable $err) {
        unlink($destination);
        throw $err;
    }
    $old = avatarFile($previous);
    if ($old) {
        $linked = getRow('SELECT id FROM users WHERE avatar_path = ? LIMIT 1', [$previous]);
        if (!$linked) @unlink($old);
    }
}

try {
    if ($action === 'login' || $action === 'register') {
        if (!empty($_SESSION['user_id'])) redirect('index.php');
        $email = strtolower(trim(post('email')));
        $password = post('password');
        if ($action === 'login') {
            $user = getRow('SELECT id, username, `password` FROM users WHERE email = ?', [$email]);
            if (!matchingPassword($user, $password)) throw new DomainException('Nieprawidłowy e-mail lub hasło.');
            signIn((int) $user['id'], $user['username']);
            redirect('index.php');
        }
        $name = trim(post('username'));
        if (!validUsername($name)) throw new DomainException('Nazwa użytkownika musi mieć od 3 do 50 znaków.');
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Podaj poprawny adres e-mail.');
        if (!validPassword($password)) throw new DomainException('Hasło musi mieć co najmniej 8 znaków i najwyżej 255 bajtów.');
        // Zachowano zgodność z istniejącą bazą lokalną GameZone.
        changeRows('INSERT INTO users (username, email, `password`) VALUES (?, ?, ?)', [$name, $email, $password]);
        signIn((int) db()->lastInsertId(), $name);
        redirect('index.php');
    }

    if ($action === 'logout') {
        $_SESSION = [];
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
        session_destroy();
        redirect('login.php');
    }

    $user = signedInUser();
    $id = (int) $user['id'];
    if ($action === 'rename') {
        $name = trim(post('username'));
        if (!validUsername($name)) throw new DomainException('Nazwa użytkownika musi mieć od 3 do 50 znaków.');
        db()->beginTransaction();
        changeRows('UPDATE users SET username = ? WHERE id = ?', [$name, $id]);
        changeRows('UPDATE reviews SET author = ? WHERE user_id = ?', [$name, $id]);
        db()->commit();
        $_SESSION['username'] = $name;
        flash('Zapisano nazwę użytkownika.');
    } elseif ($action === 'password') {
        $new = post('new_password');
        if (!validPassword($new)) throw new DomainException('Nowe hasło musi mieć co najmniej 8 znaków i najwyżej 255 bajtów.');
        if (!hash_equals($new, post('confirm_password'))) throw new DomainException('Nowe hasła nie są takie same.');
        db()->beginTransaction();
        $stored = getRow('SELECT `password` FROM users WHERE id = ? FOR UPDATE', [$id]);
        if (!matchingPassword($stored, post('current_password'))) throw new DomainException('Obecne hasło jest nieprawidłowe.');
        if (hash_equals($stored['password'], $new)) throw new DomainException('Nowe hasło musi się różnić od obecnego.');
        changeRows('UPDATE users SET `password` = ? WHERE id = ?', [$new, $id]);
        db()->commit();
        signIn($id, $user['username']);
        flash('Zmieniono hasło.');
    } elseif ($action === 'avatar') {
        saveAvatar($id, $user['avatar_path']);
        flash('Zapisano zdjęcie profilowe.');
    } else {
        $gameId = filter_var(post('game_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$gameId || !getRow('SELECT id FROM games WHERE id = ?', [$gameId])) throw new DomainException('Nie znaleziono gry.');
        if ($action === 'review') {
            $rating = filter_var(post('rating'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
            $content = trim(post('content'));
            $length = textLength($content);
            if (!$rating || $length < 5 || $length > 3000) throw new DomainException('Wybierz ocenę od 1 do 10 i wpisz opinię od 5 do 3000 znaków.');
            db()->beginTransaction();
            // Lock the account, so concurrent requests cannot publish two reviews.
            if (!getRow('SELECT id FROM users WHERE id = ? FOR UPDATE', [$id])) {
                throw new DomainException('Nie znaleziono konta. Zaloguj się ponownie.');
            }
            if (getRow('SELECT id FROM reviews WHERE user_id = ? AND game_id = ? LIMIT 1 FOR UPDATE', [$id, $gameId])) {
                throw new DomainException('Masz już recenzję tej gry. Możesz ją edytować w sekcji Moje recenzje.');
            }
            changeRows('INSERT INTO reviews (game_id, user_id, author, content, rating) VALUES (?, ?, ?, ?, ?)', [$gameId, $id, $user['username'], $content, $rating]);
            db()->commit();
            flash('Opinia została opublikowana.');
        } else {
            $add = $action === 'favorite' && post('favorite') === '1';
            if ($add) {
                changeRows('INSERT INTO favorites (user_id, game_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE game_id = game_id', [$id, $gameId]);
            } else {
                changeRows('DELETE FROM favorites WHERE user_id = ? AND game_id = ?', [$id, $gameId]);
            }
            if (post('section') === 'favorites' && $action === 'favorite') $returnTo = 'index.php?unfavorite=1#favorites';
            $message = $add ? 'Dodano grę do ulubionych.' : 'Usunięto grę z ulubionych.';
            if ($favoriteJson) jsonReply(['favorite' => $add, 'message' => $message]);
            flash($message);
        }
    }
} catch (DomainException $error) {
    rollbackIfNeeded();
    if ($favoriteJson) jsonReply(['error' => $error->getMessage()], 422);
    flash($error->getMessage(), true);
} catch (PDOException $error) {
    rollbackIfNeeded();
    $duplicate = ($error->errorInfo[1] ?? null) === 1062;
    error_log('GameZone database operation error: ' . $error->getCode());
    if ($favoriteJson) jsonReply(['error' => 'Nie udało się zmienić ulubionych. Spróbuj ponownie.'], 500);
    flash($duplicate ? 'Nazwa użytkownika lub e-mail są już zajęte.' : 'Nie udało się zapisać zmian. Sprawdź bazę GameZone.', true);
} catch (Throwable $error) {
    rollbackIfNeeded();
    error_log('GameZone operation error: ' . get_class($error));
    if ($favoriteJson) jsonReply(['error' => 'Nie udało się zmienić ulubionych. Spróbuj ponownie.'], 500);
    flash('Nie udało się wykonać tej operacji.', true);
}
redirect($returnTo);

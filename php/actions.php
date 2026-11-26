<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/recovery.php';
verifyPost();
$action = post('action');
$destinations = [
    'login' => 'login.php', 'register' => 'register.php',
    'logout' => 'profile.php', 'rename' => 'profile.php?rename=1#settings',
    'password' => 'profile.php?password=1#password-settings', 'avatar' => 'profile.php?avatar=1#photo',
    'generate_recovery' => 'profile.php?recovery=1#account-info',
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
    if (!$user || !isset($user['password']) || !is_string($user['password']) || $user['password'] === '') return false;
    $stored = $user['password'];
    if (password_get_info($stored)['algo'] !== null) {
        return password_verify($password, $stored);
    }
    // Zgodność ze starszą bazą GameZone (hasła jawne). Po udanym logowaniu migrujemy na hash.
    return hash_equals($stored, $password);
}

function upgradePasswordIfNeeded(int $id, string $stored, string $plain): void
{
    if (password_get_info($stored)['algo'] === null || password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        changeRows('UPDATE users SET `password` = ? WHERE id = ?', [password_hash($plain, PASSWORD_DEFAULT), $id]);
    }
}

function validPassword(string $password): bool
{
    return textLength($password) >= 8 && strlen($password) <= 72;
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
        $accountType = post('account_type');
        if (!in_array($accountType, ['user', 'admin'], true)) throw new DomainException('Wybierz rodzaj konta.');
        $returnTo = ($action === 'login' ? 'login-' : 'register-') . $accountType . '.php';
        $email = strtolower(trim(post('email')));
        $password = post('password');
        if ($action === 'login') {
            $account = getRow('SELECT id, username, `password`, role FROM users WHERE email = ? LIMIT 1', [$email]);
            if (!matchingPassword($account, $password) || ($account['role'] ?? '') !== $accountType) {
                if (empty($_SESSION['gz_login_fail_since']) || time() - (int) $_SESSION['gz_login_fail_since'] > 1800) {
                    $_SESSION['gz_login_fail_since'] = time();
                    $_SESSION['gz_login_fail_count'] = 0;
                }
                $_SESSION['gz_login_fail_count'] = min(100, (int) ($_SESSION['gz_login_fail_count'] ?? 0) + 1);
                throw new DomainException('Nieprawidłowe dane logowania lub wybrany rodzaj konta.');
            }
            unset($_SESSION['gz_login_fail_since'], $_SESSION['gz_login_fail_count']);
            upgradePasswordIfNeeded((int) $account['id'], (string) $account['password'], $password);
            signIn((int) $account['id'], $account['username']);
            if (!recoveryCodeIsPresent((int) $account['id'])) {
                redirect('profile.php?recovery=required#account-info');
            }
            redirect($accountType === 'admin' ? 'admin.php' : 'index.php');
        }
        $name = trim(post('username'));
        if (!validUsername($name)) throw new DomainException('Nazwa użytkownika musi mieć od 3 do 50 znaków.');
        if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Podaj poprawny adres e-mail.');
        if (!validPassword($password) || ($accountType === 'admin' && textLength($password) < 12)) {
            throw new DomainException($accountType === 'admin' ? 'Hasło administratora musi mieć 12–72 znaki.' : 'Hasło musi mieć 8–72 znaki.');
        }
        if ($accountType === 'user') {
            // Rola ustawiona na serwerze, a nie pobierana bezpośrednio z dowolnego pola POST.
            db()->beginTransaction();
            changeRows('INSERT INTO users (username, email, `password`, role) VALUES (?, ?, ?, ?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'user']);
            $newId = (int) db()->lastInsertId();
            issueRecoveryCode($newId);
            db()->commit();
            signIn($newId, $name);
            redirect('profile.php#account-info');
        }
        $invite = trim(post('invite_code'));
        if (!preg_match('/\A[a-fA-F0-9]{64}\z/', $invite)) throw new DomainException('Nieprawidłowy lub wygasły kod zaproszenia.');
        db()->beginTransaction();
        $invitation = getRow('SELECT id FROM admin_invites WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE',
            [hash('sha256', strtolower($invite))]);
        if (!$invitation) throw new DomainException('Nieprawidłowy lub wygasły kod zaproszenia.');
        changeRows('INSERT INTO users (username, email, `password`, role) VALUES (?, ?, ?, ?)',
            [$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
        $newId = (int) db()->lastInsertId();
        changeRows('UPDATE admin_invites SET used_by = ?, used_at = NOW() WHERE id = ? AND used_at IS NULL', [$newId, (int) $invitation['id']]);
        issueRecoveryCode($newId);
        db()->commit();
        signIn($newId, $name);
        redirect('profile.php#account-info');
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
    // Starsze konta, ktore nie maja kodu, moga zmienic haslo,
    // wylogowac sie lub jednorazowo wygenerowac kod. Pozostale akcje czekaja.
    if (!recoveryCodeIsPresent($id) && !in_array($action, ['password', 'generate_recovery'], true)) {
        throw new DomainException('Najpierw wygeneruj kod odzyskiwania w Informacjach o koncie.');
    }
    if ($action === 'generate_recovery') {
        generateRecoveryCodeOnce($id);
        flash('Kod odzyskiwania utworzony. Zapisz go w bezpiecznym miejscu.');
    } elseif ($action === 'rename') {
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
        if (!validPassword($new)) throw new DomainException('Nowe hasło musi mieć od 8 do 72 znaków.');
        if (!hash_equals($new, post('confirm_password'))) throw new DomainException('Nowe hasła nie są takie same.');
        db()->beginTransaction();
        $stored = getRow('SELECT `password` FROM users WHERE id = ? FOR UPDATE', [$id]);
        if (!matchingPassword($stored, post('current_password'))) throw new DomainException('Obecne hasło jest nieprawidłowe.');
        if (matchingPassword($stored, $new)) throw new DomainException('Nowe hasło musi się różnić od obecnego.');
        if (isAdmin($user) && textLength($new) < 12) throw new DomainException('Administrator potrzebuje hasła o długości co najmniej 12 znaków.');
        changeRows('UPDATE users SET `password` = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
        // Konta ze starym kodem dostaja nowy; konta bez kodu musza kliknac Generuj kod.
        $hadCode = recoveryCodeIsPresent($id);
        if ($hadCode) issueRecoveryCode($id);
        db()->commit();
        signIn($id, $user['username']);
        $returnTo = 'profile.php?password=1#account-info';
        flash($hadCode
            ? 'Zmieniono hasło. Nowy kod odzyskiwania jest widoczny w Informacjach o koncie.'
            : 'Zmieniono hasło. Teraz kliknij Generuj kod w Informacjach o koncie.');
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

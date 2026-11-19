<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';

$user = signedInUser();
if (!footerAdAdminAllowed($user)) {
    http_response_code(403);
    pageStart('Brak dostępu', 'ad-admin', $user);
    echo '<main class="auth-page"><h1>Brak dostępu</h1><p class="notice error">Tylko konto wybrane w konfiguracji może zmieniać reklamy.</p></main>';
    pageEnd();
    exit;
}

function uploadedFooterAdImage(?array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException('Plik jest za duży. Wybierz zdjęcie do 2 MB.');
    }
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Najpierw wybierz zdjęcie z komputera.');
    }
    $path = $file['tmp_name'] ?? null;
    if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        throw new InvalidArgumentException('Nie udało się przesłać zdjęcia. Wybierz plik ponownie.');
    }
    $bytes = @file_get_contents($path, false, null, 0, FOOTER_AD_MAX_IMAGE_BYTES + 1);
    if ($bytes === false || $bytes === '') {
        throw new InvalidArgumentException('Nie da się odczytać pliku. Wybierz inne zdjęcie.');
    }
    if (strlen($bytes) > FOOTER_AD_MAX_IMAGE_BYTES) {
        throw new InvalidArgumentException('Plik jest za duży. Wybierz zdjęcie do 2 MB.');
    }
    $image = @getimagesizefromstring($bytes);
    if (!in_array($image['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Wybierz poprawne zdjęcie JPG, PNG lub WebP.');
    }
    return $bytes;
}

$ads = [];
$issue = null;
try {
    $ads = getRows('SELECT id, title, is_active FROM footer_ads ORDER BY id ASC');
} catch (PDOException $exception) {
    if ((string) $exception->getCode() === '42S02') {
        $issue = 'Nie ma jeszcze tabeli reklam. Zaimportuj do bazy GameZone plik reklamy_migracja.sql.';
    } else {
        error_log('GameZone ad editor list: ' . $exception->getCode());
        $issue = 'Nie udało się pobrać reklam z bazy.';
    }
    http_response_code(503);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $issue === null) {
    if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('Serwer nie przyjął pliku. Spróbuj przesłać mniejsze zdjęcie (do 2 MB).', true);
        redirect('footer-ad-admin.php');
    }
    verifyPost();
    $id = post('ad_id');
    $byId = array_column($ads, null, 'id');
    try {
        if (!isset($byId[$id])) {
            throw new InvalidArgumentException('Nie znaleziono tej reklamy.');
        }
        $file = $_FILES['ad_photo'] ?? null;
        $bytes = uploadedFooterAdImage(is_array($file) ? $file : null);
        $statement = db()->prepare('UPDATE footer_ads SET image_data = ? WHERE id = ?');
        $statement->bindValue(1, $bytes, PDO::PARAM_LOB);
        $statement->bindValue(2, $id, PDO::PARAM_STR);
        $statement->execute();
        if ($statement->rowCount() === 0 && !getRow('SELECT id FROM footer_ads WHERE id = ?', [$id])) {
            throw new InvalidArgumentException('Nie znaleziono tej reklamy.');
        }
        flash('Zapisano zdjęcie reklamy „' . $byId[$id]['title'] . '”.');
    } catch (InvalidArgumentException $exception) {
        flash($exception->getMessage(), true);
    } catch (PDOException $exception) {
        error_log('GameZone ad editor save: ' . $exception->getCode());
        flash('Nie udało się zapisać zdjęcia w bazie. Spróbuj ponownie.', true);
    }
    redirect('footer-ad-admin.php');
}

pageStart('Zdjęcia reklam', 'ad-admin', $user);
?>
<main class="auth-page gz-ad-editor">
    <h1>Zdjęcia reklam</h1>
    <?php showFlash(); ?>
    <?php if ($issue !== null): ?>
        <p class="notice error" role="status"><?= h($issue) ?></p>
    <?php elseif (!$ads): ?>
        <p class="notice">Nie ma reklam do edycji.</p>
    <?php else: ?>
        <small class="gz-ad-editor__hint">Wybierz własny plik JPG, PNG lub WebP (do 2 MB). Zmieni się tylko zdjęcie, link pozostanie taki sam.</small>
        <?php foreach ($ads as $item): ?>
        <form class="panel gz-ad-editor__item" method="post" enctype="multipart/form-data"
              action="footer-ad-admin.php" aria-labelledby="ad-title-<?= h($item['id']) ?>">
            <?php csrfField(); ?>
            <input type="hidden" name="ad_id" value="<?= h($item['id']) ?>">
            <h2 id="ad-title-<?= h($item['id']) ?>"><?= h($item['title']) ?></h2>
            <?php if ($item['is_active']): ?>
                <img class="gz-footer-ad__image" src="<?= h('footer-ad-image.php?id=' . $item['id']) ?>"
                     alt="Obecne zdjęcie reklamy" width="256" height="128" loading="lazy">
            <?php else: ?>
                <small class="muted">Ta reklama jest wyłączona.</small>
            <?php endif; ?>
            <label for="ad-photo-<?= h($item['id']) ?>">Wybierz zdjęcie</label>
            <input id="ad-photo-<?= h($item['id']) ?>" type="file" name="ad_photo"
                   accept="image/jpeg,image/png,image/webp" required>
            <button type="submit" class="primary">Zapisz zdjęcie</button>
        </form>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php pageEnd(); ?>

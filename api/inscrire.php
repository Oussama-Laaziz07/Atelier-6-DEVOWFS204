<?php
declare(strict_types=1);

require_once __DIR__ . '/_response.php';
requestMethod('POST');

$nom = trim((string) ($_POST['nom'] ?? ''));
$prenom = trim((string) ($_POST['prenom'] ?? ''));
$ville = trim((string) ($_POST['ville'] ?? ''));
$photo = $_FILES['photo'] ?? null;

foreach (['Nom' => $nom, 'Prénom' => $prenom, 'Ville' => $ville] as $label => $value) {
    if ($value === '' || mb_strlen($value) > 100) {
        jsonResponse(422, ['error' => $label . ' est obligatoire et doit contenir au maximum 100 caractères.']);
    }
}

if (!$photo || !isset($photo['error']) || is_array($photo['error']) || $photo['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(422, ['error' => 'Sélectionnez une photo valide.']);
}
if (($photo['size'] ?? 0) < 1 || $photo['size'] > 5 * 1024 * 1024 || !is_uploaded_file($photo['tmp_name'])) {
    jsonResponse(422, ['error' => 'La photo doit peser moins de 5 Mo.']);
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowedTypes[$mime])) {
    jsonResponse(422, ['error' => 'Formats acceptés : JPG, PNG ou WebP.']);
}

$cloudinaryUrl = getenv('CLOUDINARY_URL') ?: '';
if ($cloudinaryUrl === '') {
    jsonResponse(500, ['error' => 'CLOUDINARY_URL doit être configurée dans les variables d’environnement.']);
}

try {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Dépendances absentes. Exécutez composer install avant le déploiement.');
    }
    require_once $autoload;

    // The SDK reads CLOUDINARY_URL directly from the process environment.
    $cloudinary = new Cloudinary\Cloudinary();
    $upload = $cloudinary->uploadApi()->upload($photo['tmp_name'], [
        'folder' => 'atelier6-stagiaires',
        'resource_type' => 'image',
        'allowed_formats' => ['jpg', 'png', 'webp'],
        'transformation' => [['width' => 1200, 'height' => 1200, 'crop' => 'limit', 'quality' => 'auto', 'fetch_format' => 'auto']],
    ]);
    $photoUrl = (string) ($upload['secure_url'] ?? '');
    if ($photoUrl === '' || !str_starts_with($photoUrl, 'https://')) {
        throw new RuntimeException('Cloudinary n’a pas renvoyé une URL sécurisée.');
    }

    require_once dirname(__DIR__) . '/config/db.php';
    $db = getDatabase();
    $statement = $db->prepare('INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (:nom, :prenom, :ville, :photo)');
    $statement->execute(['nom' => $nom, 'prenom' => $prenom, 'ville' => $ville, 'photo' => $photoUrl]);

    jsonResponse(201, ['message' => 'Inscription enregistrée avec succès.', 'id' => (int) $db->lastInsertId()]);
} catch (Throwable $error) {
    error_log('[atelier6] ' . $error->getMessage());
    jsonResponse(500, ['error' => 'L’inscription n’a pas pu être enregistrée. Vérifiez la configuration du serveur.']);
}

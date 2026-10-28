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

$cloudinaryUrl = trim((string) (getenv('CLOUDINARY_URL') ?: ''));
$cloudinaryCloudName = trim((string) (getenv('CLOUDINARY_CLOUD_NAME') ?: ''));
$cloudinaryApiKey = trim((string) (getenv('CLOUDINARY_API_KEY') ?: ''));
$cloudinaryApiSecret = trim((string) (getenv('CLOUDINARY_API_SECRET') ?: ''));

$stage = 'configuration Cloudinary';
try {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Le SDK Cloudinary est absent : vérifiez que Composer installe les dépendances du projet sur Vercel.');
    }
    require_once $autoload;

    $parts = $cloudinaryUrl !== '' ? parse_url($cloudinaryUrl) : false;
    $validUrl = is_array($parts)
        && ($parts['scheme'] ?? '') === 'cloudinary'
        && !empty($parts['host'])
        && !empty($parts['user'])
        && !empty($parts['pass']);
    $validSeparateConfig = $cloudinaryCloudName !== '' && $cloudinaryApiKey !== '' && $cloudinaryApiSecret !== '';

    if ($validUrl) {
        $cloudinaryConfig = [
            'cloud' => [
                'cloud_name' => $parts['host'],
                'api_key' => rawurldecode($parts['user']),
                'api_secret' => rawurldecode($parts['pass']),
            ],
            'url' => ['secure' => true],
        ];
    } elseif ($validSeparateConfig) {
        $cloudinaryConfig = [
            'cloud' => [
                'cloud_name' => $cloudinaryCloudName,
                'api_key' => $cloudinaryApiKey,
                'api_secret' => $cloudinaryApiSecret,
            ],
            'url' => ['secure' => true],
        ];
    } elseif ($cloudinaryUrl !== '') {
        throw new RuntimeException('CLOUDINARY_URL doit être l’URL complète cloudinary://API_KEY:API_SECRET@CLOUD_NAME, pas l’API Key seule.');
    } else {
        throw new RuntimeException('Configurez les trois variables CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY et CLOUDINARY_API_SECRET, ou CLOUDINARY_URL complète. Une API Key seule ne suffit pas.');
    }

    $cloudinary = new Cloudinary\Cloudinary($cloudinaryConfig);
    $stage = 'téléversement Cloudinary';
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

    $stage = 'connexion à Aiven';
    require_once dirname(__DIR__) . '/config/db.php';
    $db = getDatabase();
    $stage = 'insertion dans la table stagiaires';
    $statement = $db->prepare('INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (:nom, :prenom, :ville, :photo)');
    $statement->execute(['nom' => $nom, 'prenom' => $prenom, 'ville' => $ville, 'photo' => $photoUrl]);

    jsonResponse(201, ['message' => 'Inscription enregistrée avec succès.', 'id' => (int) $db->lastInsertId()]);
} catch (Throwable $error) {
    error_log('[atelier6][' . $stage . '] ' . $error->getMessage());
    $help = $stage === 'configuration Cloudinary'
        ? $error->getMessage()
        : ($stage === 'téléversement Cloudinary'
            ? 'Vérifiez que le cloud name, l’API Key et l’API Secret proviennent du même environnement Cloudinary.'
        : ($stage === 'connexion à Aiven'
            ? 'Vérifiez les variables DB_*, le certificat CA Aiven et la connexion MySQL.'
            : 'Vérifiez que la table stagiaires contient les colonnes attendues.'));
    jsonResponse(500, ['error' => 'Échec pendant ' . $stage . '. ' . $help]);
}

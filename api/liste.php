<?php
declare(strict_types=1);

require_once __DIR__ . '/_response.php';
requestMethod('GET');

try {
    require_once dirname(__DIR__) . '/config/db.php';
    $statement = getDatabase()->query('SELECT id, nom, prenom, ville, photo, date_inscription FROM stagiaires ORDER BY date_inscription DESC, id DESC');
    jsonResponse(200, ['stagiaires' => $statement->fetchAll()]);
} catch (Throwable $error) {
    error_log('[atelier6] ' . $error->getMessage());
    jsonResponse(500, ['error' => 'La liste des stagiaires est indisponible. Vérifiez la configuration du serveur.']);
}

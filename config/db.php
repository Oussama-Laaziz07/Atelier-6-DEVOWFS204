<?php
declare(strict_types=1);

function getDatabase(): PDO
{
    $host = getenv('DB_HOST') ?: '';
    $port = getenv('DB_PORT') ?: '';
    $name = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';

    foreach (['DB_HOST' => $host, 'DB_PORT' => $port, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASSWORD' => $password] as $key => $value) {
        if ($value === '') {
            throw new RuntimeException("Configuration manquante : {$key}.");
        }
    }

    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('DB_PORT doit être un numéro de port valide.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $name);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];

    $caPath = dirname(__DIR__) . '/certs/ca.pem';
    $caContents = getenv('DB_CA_CERT') ?: '';
    if ($caContents !== '') {
        $caPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'aiven-ca-' . hash('sha256', $caContents) . '.pem';
        if (!is_file($caPath) && file_put_contents($caPath, $caContents, LOCK_EX) === false) {
            throw new RuntimeException('Impossible de préparer le certificat CA Aiven.');
        }
    }

    if (is_file($caPath)) {
        if (!defined('PDO::MYSQL_ATTR_SSL_CA')) {
            throw new RuntimeException('Le pilote PDO MySQL doit être activé avec le support TLS.');
        }
        $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $caPath;
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
        }
    } else {
        throw new RuntimeException('Certificat CA Aiven absent : ajoutez certs/ca.pem ou configurez DB_CA_CERT.');
    }

    return new PDO($dsn, $user, $password, $options);
}

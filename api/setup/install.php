<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
    if ($schema === false) {
        jsonResponse(['success' => false, 'error' => 'No se pudo leer el archivo schema.sql'], 500);
    }

    $statements = explode(';', $schema);
    $errors = [];
    $executed = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt) {
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Base de datos instalada correctamente',
        'statements' => $executed,
        'errors' => $errors
    ]);

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()], 500);
}

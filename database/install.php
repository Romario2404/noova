<?php
/**
 * NOOVA S.A.C. - Database Installer
 * Run once to create database and tables.
 */

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'noova_db';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $statements = explode(';', $schema);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                // Ignore "already exists" errors for inserts
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "Database installed successfully!\n";
} catch (PDOException $e) {
    die("Install failed: " . $e->getMessage() . "\n");
}

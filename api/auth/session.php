<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$user = getCurrentUser();

if ($user) {
    unset($user['password_hash']);
    jsonResponse([
        'logged_in' => true,
        'user' => $user,
        'csrf' => $_SESSION['csrf_token'] ?? ''
    ]);
} else {
    jsonResponse([
        'logged_in' => false,
        'user' => null
    ]);
}

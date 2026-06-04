<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

if (isset($_SESSION['user_id'])) {
    logActivity('logout', 'usuario', $_SESSION['user_id'], 'Cierre de sesión');

    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM sessions WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {}
}

$_SESSION = [];
session_destroy();

setcookie(session_name(), '', time() - 3600, '/');

jsonResponse([
    'success' => true,
    'message' => 'Sesión cerrada exitosamente'
]);

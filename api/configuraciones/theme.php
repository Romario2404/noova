<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($data['theme'])) {
    jsonError('Theme es requerido');
}

try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE usuarios SET theme = ? WHERE id = ?");
    $stmt->execute([$data['theme'], $user['id']]);

    jsonResponse([
        'success' => true,
        'message' => 'Theme actualizado'
    ]);

} catch (Exception $e) {
    jsonError('Error al actualizar theme', 500);
}

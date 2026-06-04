<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($data['mode'])) {
    jsonError('Mode es requerido');
}

try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE usuarios SET modo = ? WHERE id = ?");
    $stmt->execute([$data['mode'], $user['id']]);

    jsonResponse([
        'success' => true,
        'message' => 'Modo actualizado'
    ]);

} catch (Exception $e) {
    jsonError('Error al actualizar modo', 500);
}

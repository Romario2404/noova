<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = $_POST;

if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true) ?? [];
}

$error = validateInput($data, [
    'destinatario_id' => 'required|numeric',
    'asunto' => 'required',
    'cuerpo' => 'required'
]);

if ($error) {
    jsonError($error);
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
    $stmt->execute([$data['destinatario_id']]);
    if (!$stmt->fetch()) {
        jsonError('El destinatario no existe o no está activo');
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO mensajes (remitente_id, asunto, cuerpo, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $data['asunto'], $data['cuerpo']]);
    $mensajeId = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO mensaje_destinatarios (mensaje_id, destinatario_id, leido, created_at)
        VALUES (?, ?, 0, NOW())
    ");
    $stmt->execute([$mensajeId, $data['destinatario_id']]);

    if (isset($data['cc']) && is_array($data['cc'])) {
        $stmt = $db->prepare("
            INSERT INTO mensaje_destinatarios (mensaje_id, destinatario_id, leido, created_at)
            VALUES (?, ?, 0, NOW())
        ");
        foreach ($data['cc'] as $ccId) {
            $stmt->execute([$mensajeId, $ccId]);
        }
    }

    $db->commit();

    logActivity('enviar_mensaje', 'mensaje', $mensajeId, "Mensaje enviado a usuario #{$data['destinatario_id']}: {$data['asunto']}");

    jsonResponse([
        'success' => true,
        'message' => 'Mensaje enviado correctamente',
        'id' => $mensajeId
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Error al enviar mensaje: ' . $e->getMessage(), 500);
}

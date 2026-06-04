<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();
$db = getDB();

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $chatId = (int)($_GET['chat_id'] ?? 0);
    if (!$chatId) jsonError('ID de chat requerido');

    // Verify user is participant
    $stmt = $db->prepare("SELECT 1 FROM chat_participantes WHERE chat_id = ? AND usuario_id = ?");
    $stmt->execute([$chatId, $user['id']]);
    if (!$stmt->fetch()) jsonError('No eres miembro de este chat', 403);

    $stmt = $db->prepare("
        SELECT cm.*, u.nombres, u.apellidos, u.foto
        FROM chat_mensajes cm
        LEFT JOIN usuarios u ON cm.usuario_id = u.id
        WHERE cm.chat_id = ?
        ORDER BY cm.created_at ASC
    ");
    $stmt->execute([$chatId]);
    $mensajes = $stmt->fetchAll();

    // Update last read
    $stmt = $db->prepare("UPDATE chat_participantes SET ultima_lectura = NOW() WHERE chat_id = ? AND usuario_id = ?");
    $stmt->execute([$chatId, $user['id']]);

    jsonResponse($mensajes);
} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $chatId = (int)($input['chat_id'] ?? 0);
    $mensaje = trim($input['mensaje'] ?? '');

    if (!$chatId) jsonError('ID de chat requerido');
    if (empty($mensaje)) jsonError('El mensaje no puede estar vacío');

    // Verify user is participant
    $stmt = $db->prepare("SELECT 1 FROM chat_participantes WHERE chat_id = ? AND usuario_id = ?");
    $stmt->execute([$chatId, $user['id']]);
    if (!$stmt->fetch()) jsonError('No eres miembro de este chat', 403);

    $stmt = $db->prepare("INSERT INTO chat_mensajes (chat_id, usuario_id, mensaje) VALUES (?, ?, ?)");
    $stmt->execute([$chatId, $user['id'], $mensaje]);
    $msgId = (int)$db->lastInsertId();

    logActivity('mensaje_enviado', 'chats', $chatId);

    $stmt = $db->prepare("
        SELECT cm.*, u.nombres, u.apellidos, u.foto
        FROM chat_mensajes cm
        LEFT JOIN usuarios u ON cm.usuario_id = u.id
        WHERE cm.id = ?
    ");
    $stmt->execute([$msgId]);
    jsonResponse($stmt->fetch(), 201);
} else {
    jsonError('Método no permitido', 405);
}

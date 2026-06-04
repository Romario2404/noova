<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();
$db = getDB();

$stmt = $db->prepare("
    SELECT c.*, 
           (SELECT cm.mensaje FROM chat_mensajes cm WHERE cm.chat_id = c.id ORDER BY cm.created_at DESC LIMIT 1) as ultimo_mensaje,
           (SELECT cm.created_at FROM chat_mensajes cm WHERE cm.chat_id = c.id ORDER BY cm.created_at DESC LIMIT 1) as ultimo_mensaje_at,
           (SELECT COUNT(*) FROM chat_mensajes cm WHERE cm.chat_id = c.id AND cm.created_at > COALESCE(cp.ultima_lectura, '1970-01-01')) as no_leidos
    FROM chats c
    JOIN chat_participantes cp ON c.id = cp.chat_id AND cp.usuario_id = ?
    ORDER BY ultimo_mensaje_at DESC
");
$stmt->execute([$user['id']]);
$chats = $stmt->fetchAll();

// Get participants for each chat
$stmtPart = $db->prepare("
    SELECT u.id, u.nombres, u.apellidos, u.foto
    FROM chat_participantes cp
    JOIN usuarios u ON cp.usuario_id = u.id
    WHERE cp.chat_id = ?
");

foreach ($chats as &$chat) {
    $stmtPart->execute([$chat['id']]);
    $chat['participantes'] = $stmtPart->fetchAll();
}

jsonResponse($chats);

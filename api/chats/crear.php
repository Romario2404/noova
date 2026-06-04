<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$tipo = $input['tipo'] ?? 'individual';
$nombre = $input['nombre'] ?? null;
$participantes = $input['participantes'] ?? [];
$proyectoId = $input['proyecto_id'] ?? null;

if ($tipo !== 'individual' && empty($nombre)) jsonError('Nombre requerido para chats grupales');
if ($tipo === 'individual' && count($participantes) !== 1) jsonError('Se requiere exactamente 1 participante para chat individual');

try {
    $db = getDB();

    // For individual chats, check if one already exists
    if ($tipo === 'individual') {
        $stmt = $db->prepare("
            SELECT c.id FROM chats c
            JOIN chat_participantes cp1 ON c.id = cp1.chat_id AND cp1.usuario_id = ?
            JOIN chat_participantes cp2 ON c.id = cp2.chat_id AND cp2.usuario_id = ?
            WHERE c.tipo = 'individual'
        ");
        $stmt->execute([$user['id'], $participantes[0]]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            jsonResponse(['id' => (int)$existing, 'existente' => true]);
            return;
        }
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO chats (proyecto_id, tipo, nombre) VALUES (?, ?, ?)");
    $stmt->execute([$proyectoId, $tipo, $nombre]);
    $chatId = (int)$db->lastInsertId();

    // Add creator + participants
    $todosParticipantes = array_unique(array_merge([$user['id']], $participantes));
    $stmt = $db->prepare("INSERT INTO chat_participantes (chat_id, usuario_id) VALUES (?, ?)");
    foreach ($todosParticipantes as $pid) {
        $stmt->execute([$chatId, (int)$pid]);
    }

    $db->commit();
    logActivity('chat_creado', 'chats', $chatId);
    jsonResponse(['id' => $chatId, 'mensaje' => 'Chat creado correctamente'], 201);
} catch (Exception $e) {
    $db->rollBack();
    jsonError('Error al crear chat: ' . $e->getMessage(), 500);
}

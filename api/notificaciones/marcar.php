<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/notificaciones/Notificaciones.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$notif = new Notificaciones();

if (!empty($input['todas'])) {
    $notif->marcarTodasLeidas($user['id']);
    jsonResponse(['mensaje' => 'Todas las notificaciones marcadas como leídas']);
} else {
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonError('ID requerido');
    $notif->marcarLeida($id, $user['id']);
    jsonResponse(['mensaje' => 'Notificación marcada como leída']);
}

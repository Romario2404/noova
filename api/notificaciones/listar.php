<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$soloNoLeidas = isset($_GET['solo_no_leidas']) && ($_GET['solo_no_leidas'] === 'true' || $_GET['solo_no_leidas'] === '1');

try {
    $db = getDB();

    $where = 'n.usuario_id = ?';
    $params = [$user['id']];

    if ($soloNoLeidas) {
        $where .= ' AND n.leido = 0';
    }

    $sql = "
        SELECT n.id, n.titulo, n.mensaje, n.tipo, n.icono,
               n.link, n.leido, n.leido_en, n.created_at
        FROM notificaciones n
        WHERE {$where}
        ORDER BY n.created_at DESC
        LIMIT 50
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notificaciones = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM notificaciones
        WHERE usuario_id = ? AND leido = 0
    ");
    $stmt->execute([$user['id']]);
    $unreadCount = (int)$stmt->fetch()['total'];

    jsonResponse([
        'success' => true,
        'data' => $notificaciones,
        'unread_count' => $unreadCount,
        'total' => count($notificaciones)
    ]);

} catch (Exception $e) {
    jsonError('Error al listar notificaciones: ' . $e->getMessage(), 500);
}

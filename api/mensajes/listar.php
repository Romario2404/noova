<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$tipo = $_GET['tipo'] ?? 'recibidos';

try {
    $db = getDB();

    if ($tipo === 'enviados') {
        $sql = "
            SELECT m.id, m.asunto, m.cuerpo, m.created_at,
                   u.nombres AS destinatario_nombres,
                   u.apellidos AS destinatario_apellidos,
                   u.email AS destinatario_email,
                   (SELECT COUNT(*) FROM mensaje_destinatarios WHERE mensaje_id = m.id) AS destinatarios_count
            FROM mensajes m
            LEFT JOIN mensaje_destinatarios md ON m.id = md.mensaje_id
            LEFT JOIN usuarios u ON md.destinatario_id = u.id
            WHERE m.remitente_id = ?
            ORDER BY m.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['id']]);

        $mensajes = [];
        $temp = [];
        while ($row = $stmt->fetch()) {
            $id = $row['id'];
            if (!isset($temp[$id])) {
                $temp[$id] = [
                    'id' => $row['id'],
                    'asunto' => $row['asunto'],
                    'cuerpo' => $row['cuerpo'],
                    'created_at' => $row['created_at'],
                    'destinatarios' => [],
                    'destinatarios_count' => $row['destinatarios_count']
                ];
            }
            if ($row['destinatario_nombres']) {
                $temp[$id]['destinatarios'][] = [
                    'nombres' => $row['destinatario_nombres'],
                    'apellidos' => $row['destinatario_apellidos'],
                    'email' => $row['destinatario_email']
                ];
            }
        }
        $mensajes = array_values($temp);

    } else {
        $sql = "
            SELECT m.id, m.asunto, m.cuerpo, m.created_at,
                   u.nombres AS remitente_nombres,
                   u.apellidos AS remitente_apellidos,
                   u.email AS remitente_email,
                   md.leido,
                   md.leido_en,
                   md.id AS destinatario_id
            FROM mensaje_destinatarios md
            JOIN mensajes m ON md.mensaje_id = m.id
            LEFT JOIN usuarios u ON m.remitente_id = u.id
            WHERE md.destinatario_id = ?
            ORDER BY md.leido ASC, m.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['id']]);
        $mensajes = $stmt->fetchAll();
    }

    jsonResponse([
        'success' => true,
        'data' => $mensajes,
        'tipo' => $tipo,
        'total' => count($mensajes)
    ]);

} catch (Exception $e) {
    jsonError('Error al listar mensajes: ' . $e->getMessage(), 500);
}

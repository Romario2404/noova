<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$estado = cleanInput($data['estado'] ?? '');
$respuesta = cleanInput($data['respuesta_asesor'] ?? '');

if (!$id || !in_array($estado, ['aprobado', 'rechazado', 'completado'])) {
    jsonResponse(['error' => 'Datos invalidos'], 400);
}

$db = getDB();

// Get user's rol_nombre
$stmt = $db->prepare("SELECT r.nombre as rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
$stmt->execute([$user['id']]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);
$rol_nombre = $rol ? $rol['rol_nombre'] : '';

// Get the solicitud
$stmt = $db->prepare("SELECT s.*, c.usuario_id as cliente_usuario_id FROM solicitudes s JOIN clientes c ON s.cliente_id = c.id WHERE s.id = ?");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    jsonResponse(['error' => 'Solicitud no encontrada'], 404);
}

// Check permissions
if ($rol_nombre === 'asesor') {
    $stmt = $db->prepare("SELECT id FROM asesores WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $ase = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ase || $solicitud['asesor_id'] != $ase['id']) {
        jsonResponse(['error' => 'No tienes permiso para modificar esta solicitud'], 403);
    }
} elseif ($rol_nombre === 'cliente') {
    jsonResponse(['error' => 'Los clientes no pueden cambiar el estado de solicitudes'], 403);
}

$stmt = $db->prepare("UPDATE solicitudes SET estado = ?, respuesta_asesor = ?, fecha_respuesta = NOW() WHERE id = ?");
$stmt->execute([$estado, $respuesta, $id]);

logActivity($user['id'], 'actualizar_solicitud', 'solicitudes', $id, "Solicitud $id -> $estado");

// If approved, create a project
if ($estado === 'aprobado') {
    $stmt = $db->prepare("INSERT INTO proyectos (codigo, nombre, descripcion, cliente_id, asesor_id, tipo, estado) VALUES (?, ?, ?, ?, ?, ?, 'planificacion')");
    $codigo = 'PROJ-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $nombre_proyecto = 'Proyecto ' . $solicitud['tipo_proyecto'] . ' - ' . $solicitud['razon_social'];
    $stmt->execute([$codigo, $nombre_proyecto, $solicitud['descripcion'], $solicitud['cliente_id'], $solicitud['asesor_id'], $solicitud['tipo_proyecto']]);
    $proyecto_id = $db->lastInsertId();

    // Notify cliente
    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_tipo, referencia_id) VALUES (?, 'solicitud_aprobada', 'Solicitud aprobada', ?, 'proyectos', ?)");
    $stmt->execute([$solicitud['cliente_usuario_id'], "Tu solicitud ha sido aprobada. Proyecto: $codigo", $proyecto_id]);
}

// Notify cliente on rejection
if ($estado === 'rechazado') {
    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_tipo, referencia_id) VALUES (?, 'solicitud_rechazada', 'Solicitud rechazada', ?, 'solicitudes', ?)");
    $stmt->execute([$solicitud['cliente_usuario_id'], $respuesta ? "Respuesta: $respuesta" : 'Su solicitud ha sido rechazada', $id]);
}

jsonResponse(['success' => true, 'message' => 'Solicitud actualizada']);

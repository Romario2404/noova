<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();

$data = json_decode(file_get_contents('php://input'), true);

$tipo_proyecto = cleanInput($data['tipo_proyecto'] ?? '');
$descripcion = cleanInput($data['descripcion'] ?? '');
$asesor_id = $data['asesor_id'] ?? null;

if (!$tipo_proyecto) {
    jsonResponse(['error' => 'Seleccione un tipo de proyecto'], 400);
}

$db = getDB();

// Get cliente id
$stmt = $db->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
$stmt->execute([$user['id']]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    jsonResponse(['error' => 'Perfil de cliente no encontrado'], 400);
}

// Verify asesor exists if provided
if ($asesor_id) {
    $stmt = $db->prepare("SELECT id FROM asesores WHERE id = ?");
    $stmt->execute([$asesor_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Asesor no encontrado'], 400);
    }
}

$stmt = $db->prepare("INSERT INTO solicitudes (cliente_id, asesor_id, tipo_proyecto, descripcion, estado) VALUES (?, ?, ?, ?, 'pendiente')");
$stmt->execute([$cliente['id'], $asesor_id, $tipo_proyecto, $descripcion]);
$id = $db->lastInsertId();

logActivity($user['id'], 'crear_solicitud', 'solicitudes', $id, "Solicitud creada: $tipo_proyecto");

// Notify asesor if assigned
if ($asesor_id) {
    $stmt = $db->prepare("SELECT usuario_id FROM asesores WHERE id = ?");
    $stmt->execute([$asesor_id]);
    $asesor = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($asesor) {
        $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_tipo, referencia_id) VALUES (?, 'solicitud', 'Nueva solicitud de proyecto', ?, 'solicitudes', ?)");
        $stmt->execute([$asesor['usuario_id'], "Se ha asignado una nueva solicitud", $id]);
    }
}

jsonResponse(['success' => true, 'id' => $id, 'message' => 'Solicitud enviada correctamente']);

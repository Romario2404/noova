<?php
require_once __DIR__ . '/../config.php';

$user = requireRole('admin', 'supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$error = validateInput($data, [
    'nombre' => 'required',
    'cliente_id' => 'required|numeric',
    'tipo' => 'required'
]);

if ($error) {
    jsonError($error);
}

try {
    $db = getDB();

    $codigo = 'PROJ-' . strtoupper(substr(uniqid(), -6));

    $stmt = $db->prepare("
        INSERT INTO proyectos (
            codigo, nombre, descripcion, cliente_id, asesor_id, supervisor_id,
            estado, prioridad, tipo, fecha_inicio, fecha_fin_estimada,
            presupuesto, ubicacion, notas
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $codigo,
        $data['nombre'],
        $data['descripcion'] ?? null,
        $data['cliente_id'],
        $data['asesor_id'] ?? null,
        $user['id'],
        $data['estado'] ?? 'planificacion',
        $data['prioridad'] ?? 'media',
        $data['tipo'],
        $data['fecha_inicio'] ?? null,
        $data['fecha_fin_estimada'] ?? null,
        $data['presupuesto'] ?? null,
        $data['ubicacion'] ?? null,
        $data['notas'] ?? null
    ]);

    $proyectoId = $db->lastInsertId();

    logActivity('crear_proyecto', 'proyecto', $proyectoId, "Creación de proyecto: {$data['nombre']}");

    jsonResponse([
        'success' => true,
        'message' => 'Proyecto creado exitosamente',
        'id' => $proyectoId,
        'codigo' => $codigo
    ], 201);

} catch (Exception $e) {
    jsonError('Error al crear proyecto: ' . $e->getMessage(), 500);
}

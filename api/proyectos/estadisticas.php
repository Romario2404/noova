<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/proyectos/Proyectos.php';

$user = requireAuth();

$proyectos = new Proyectos();
$estadisticas = $proyectos->getEstadisticas();

// Additional stats for authenticated users
if ($user['rol_nombre'] === 'cliente') {
    $stmt = getDB()->prepare("
        SELECT COUNT(*) as total, SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados
        FROM proyectos WHERE cliente_id IN (SELECT id FROM clientes WHERE usuario_id = ?) AND activo = 1
    ");
    $stmt->execute([$user['id']]);
    $estadisticas = $stmt->fetch();
} elseif ($user['rol_nombre'] === 'asesor') {
    $stmt = getDB()->prepare("
        SELECT COUNT(*) as total, SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados
        FROM proyectos WHERE asesor_id IN (SELECT id FROM asesores WHERE usuario_id = ?) AND activo = 1
    ");
    $stmt->execute([$user['id']]);
    $estadisticas = $stmt->fetch();
}

jsonResponse($estadisticas);

<?php
require_once __DIR__ . '/../config.php';

$user = requireRole('admin', 'supervisor');

try {
    $db = getDB();

    // General stats
    $stmt = $db->query("
        SELECT
            (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
            (SELECT COUNT(*) FROM clientes) as total_clientes,
            (SELECT COUNT(*) FROM proyectos WHERE activo = 1) as total_proyectos,
            (SELECT COUNT(*) FROM documentos) as total_documentos,
            (SELECT COUNT(*) FROM mensajes WHERE leido = 0) as mensajes_no_leidos,
            (SELECT COUNT(*) FROM notificaciones WHERE leido = 0) as notificaciones_pendientes
    ");
    $stats = $stmt->fetch();

    // Projects by status
    $stmt = $db->query("
        SELECT estado, COUNT(*) as total FROM proyectos
        WHERE activo = 1 GROUP BY estado
    ");
    $proyectosPorEstado = $stmt->fetchAll();

    // Users by role
    $stmt = $db->query("
        SELECT r.nombre as rol, COUNT(*) as total
        FROM usuarios u JOIN roles r ON u.rol_id = r.id
        WHERE u.activo = 1 GROUP BY r.nombre
    ");
    $usuariosPorRol = $stmt->fetchAll();

    // Recent activity
    $stmt = $db->query("
        SELECT al.*, u.nombres, u.apellidos
        FROM activity_logs al
        LEFT JOIN usuarios u ON al.usuario_id = u.id
        ORDER BY al.created_at DESC LIMIT 20
    ");
    $actividadReciente = $stmt->fetchAll();

    // Monthly projects (last 12 months)
    $stmt = $db->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total
        FROM proyectos
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mes ASC
    ");
    $proyectosMensuales = $stmt->fetchAll();

    jsonResponse([
        'stats' => $stats,
        'proyectos_por_estado' => $proyectosPorEstado,
        'usuarios_por_rol' => $usuariosPorRol,
        'actividad_reciente' => $actividadReciente,
        'proyectos_mensuales' => $proyectosMensuales
    ]);
} catch (Exception $e) {
    jsonError('Error al obtener estadísticas: ' . $e->getMessage(), 500);
}

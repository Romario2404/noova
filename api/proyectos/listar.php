<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

try {
    $db = getDB();
    $params = [];
    $where = ['p.activo = 1'];

    if ($user['rol_nombre'] === 'cliente') {
        $where[] = 'p.cliente_id = (SELECT id FROM clientes WHERE usuario_id = ?)';
        $params[] = $user['id'];
    } elseif ($user['rol_nombre'] === 'asesor') {
        $where[] = 'p.asesor_id = (SELECT id FROM asesores WHERE usuario_id = ?)';
        $params[] = $user['id'];
    }

    if (isset($_GET['estado']) && !empty($_GET['estado'])) {
        $where[] = 'p.estado = ?';
        $params[] = $_GET['estado'];
    }

    if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
        $where[] = 'p.tipo = ?';
        $params[] = $_GET['tipo'];
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ?)';
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }

    $whereClause = implode(' AND ', $where);

    $sql = "
        SELECT p.*,
               p.porcentaje_avance as progreso,
               c.razon_social as cliente_nombre,
               CONCAT(uc.nombres, ' ', uc.apellidos) as asesor_nombre
        FROM proyectos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN asesores a ON p.asesor_id = a.id
        LEFT JOIN usuarios uc ON a.usuario_id = uc.id
        WHERE {$whereClause}
        ORDER BY p.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $proyectos = $stmt->fetchAll();

    jsonResponse($proyectos);

} catch (Exception $e) {
    jsonError('Error al listar proyectos: ' . $e->getMessage(), 500);
}

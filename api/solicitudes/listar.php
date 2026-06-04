<?php
require_once __DIR__ . '/../config.php';
$user = requireAuth();

$db = getDB();

// Get user's rol_nombre
$stmt = $db->prepare("SELECT r.nombre as rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
$stmt->execute([$user['id']]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);
$rol_nombre = $rol ? $rol['rol_nombre'] : '';

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$asesor_id = isset($_GET['asesor_id']) ? intval($_GET['asesor_id']) : 0;
$estado = isset($_GET['estado']) ? cleanInput($_GET['estado']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$where = [];
$params = [];
$joinCliente = "JOIN clientes c ON s.cliente_id = c.id";
$joinAsesor = "LEFT JOIN asesores a ON s.asesor_id = a.id";

if ($rol_nombre === 'cliente') {
    $stmt = $db->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $cli = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cli) {
        $where[] = "s.cliente_id = ?";
        $params[] = $cli['id'];
    }
} elseif ($rol_nombre === 'asesor') {
    $stmt = $db->prepare("SELECT id FROM asesores WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $ase = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ase) {
        $where[] = "(s.asesor_id = ? OR s.asesor_id IS NULL)";
        $params[] = $ase['id'];
    }
} elseif ($rol_nombre === 'admin') {
    if ($cliente_id) { $where[] = "s.cliente_id = ?"; $params[] = $cliente_id; }
    if ($asesor_id) { $where[] = "s.asesor_id = ?"; $params[] = $asesor_id; }
}

if ($estado) { $where[] = "s.estado = ?"; $params[] = $estado; }

$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT s.*, c.razon_social, c.ruc, u_cli.nombres as cliente_nombre, u_cli.apellidos as cliente_apellido,
        a.especialidad, u_ase.nombres as asesor_nombre, u_ase.apellidos as asesor_apellido
        FROM solicitudes s
        $joinCliente
        $joinAsesor
        LEFT JOIN usuarios u_cli ON c.usuario_id = u_cli.id
        LEFT JOIN usuarios u_ase ON a.usuario_id = u_ase.id
        $sql_where
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse($solicitudes);

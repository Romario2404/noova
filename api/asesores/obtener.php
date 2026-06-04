<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/asesores/Asesores.php';

$user = requireAuth();

$id = (int)($_GET['id'] ?? 0);
$usuario_id = (int)($_GET['usuario_id'] ?? 0);
if (!$id && !$usuario_id) jsonError('ID o usuario_id requerido');

$asesores = new Asesores();
if ($usuario_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM asesores WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Asesor no encontrado para este usuario', 404);
    $id = (int)$row['id'];
}
$data = $asesores->obtener($id);
if (!$data) jsonError('Asesor no encontrado', 404);

$data['clientes'] = $asesores->getClientes($id);
$data['proximos_vencimientos'] = $asesores->getProximosVencimientos($id);
jsonResponse($data);

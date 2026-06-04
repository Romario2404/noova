<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/clientes/Clientes.php';

$user = requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$clientes = new Clientes();

// Client role can only see own profile
if ($user['rol_nombre'] === 'cliente') {
    $stmt = getDB()->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $clienteId = $stmt->fetchColumn();
    if ((int)$clienteId !== $id) jsonError('No autorizado', 403);
}

$data = $clientes->obtener($id);
if (!$data) jsonError('Cliente no encontrado', 404);

$data['asesores'] = $clientes->getAsesores($id);
jsonResponse($data);

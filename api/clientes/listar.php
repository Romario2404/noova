<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/clientes/Clientes.php';

$user = requireAuth();
$clientes = new Clientes();

$filtros = [];
if (!empty($_GET['search'])) $filtros['search'] = $_GET['search'];
if (!empty($_GET['rubro'])) $filtros['rubro'] = $_GET['rubro'];

// If client role, only show own client profile
if ($user['rol_nombre'] === 'cliente') {
    $stmt = getDB()->prepare("SELECT id FROM clientes WHERE usuario_id = ?");
    $stmt->execute([$user['id']]);
    $clienteId = $stmt->fetchColumn();
    if ($clienteId) {
        $data = $clientes->obtener((int)$clienteId);
        jsonResponse($data ? [$data] : []);
        return;
    }
}

$data = $clientes->listar($filtros);
jsonResponse($data);

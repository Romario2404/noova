<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/clientes/Clientes.php';

$user = requireRole('admin', 'supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int)($input['id'] ?? $_GET['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$clientes = new Clientes();
try {
    $clientes->actualizar($id, $input);
    jsonResponse(['mensaje' => 'Cliente actualizado correctamente']);
} catch (Exception $e) {
    jsonError('Error al actualizar cliente: ' . $e->getMessage(), 500);
}

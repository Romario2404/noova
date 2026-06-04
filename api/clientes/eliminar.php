<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/clientes/Clientes.php';

$user = requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$clientes = new Clientes();
try {
    $clientes->eliminar($id);
    jsonResponse(['mensaje' => 'Cliente eliminado correctamente']);
} catch (Exception $e) {
    jsonError('Error al eliminar cliente: ' . $e->getMessage(), 500);
}

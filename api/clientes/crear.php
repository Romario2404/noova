<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/clientes/Clientes.php';

$user = requireRole('admin', 'supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$error = validateInput($input, ['razon_social' => 'required']);
if ($error) jsonError($error);

$clientes = new Clientes();
try {
    $id = $clientes->crear($input);
    jsonResponse(['id' => $id, 'mensaje' => 'Cliente creado correctamente'], 201);
} catch (Exception $e) {
    jsonError('Error al crear cliente: ' . $e->getMessage(), 500);
}

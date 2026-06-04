<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/configuraciones/Configuraciones.php';

$user = requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$config = new Configuraciones();
try {
    $config->actualizarMultiples($input);
    jsonResponse(['mensaje' => 'Configuraciones guardadas correctamente']);
} catch (Exception $e) {
    jsonError('Error al guardar configuraciones: ' . $e->getMessage(), 500);
}

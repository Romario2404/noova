<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/asesores/Asesores.php';

$user = requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$asesores = new Asesores();
try {
    $id = $asesores->crear($input);
    jsonResponse(['id' => $id, 'mensaje' => 'Asesor creado correctamente'], 201);
} catch (Exception $e) {
    jsonError('Error al crear asesor: ' . $e->getMessage(), 500);
}

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/asesores/Asesores.php';

$user = requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$asesores = new Asesores();
try {
    $asesores->eliminar($id);
    jsonResponse(['mensaje' => 'Asesor eliminado correctamente']);
} catch (Exception $e) {
    jsonError('Error al eliminar asesor: ' . $e->getMessage(), 500);
}

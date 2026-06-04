<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/proyectos/Proyectos.php';

$user = requireRole('admin', 'supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$proyectos = new Proyectos();
try {
    $proyectos->eliminar($id);
    jsonResponse(['mensaje' => 'Proyecto eliminado correctamente']);
} catch (Exception $e) {
    jsonError('Error al eliminar proyecto: ' . $e->getMessage(), 500);
}

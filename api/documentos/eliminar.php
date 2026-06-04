<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/documentos/Documentos.php';

$user = requireRole('admin', 'supervisor');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$documentos = new Documentos();
try {
    $documentos->eliminar($id);
    jsonResponse(['mensaje' => 'Documento eliminado correctamente']);
} catch (Exception $e) {
    jsonError('Error al eliminar documento: ' . $e->getMessage(), 500);
}

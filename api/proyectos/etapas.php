<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/proyectos/Proyectos.php';

$user = requireRole('admin', 'supervisor', 'asesor');

$proyectos = new Proyectos();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $proyectoId = (int)($_GET['proyecto_id'] ?? 0);
    if (!$proyectoId) jsonError('ID de proyecto requerido');
    jsonResponse($proyectos->getEtapas($proyectoId));
} elseif ($metodo === 'PUT' || $metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $etapaId = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$etapaId) jsonError('ID de etapa requerido');
    try {
        $proyectos->actualizarEtapa($etapaId, $input);
        jsonResponse(['mensaje' => 'Etapa actualizada correctamente']);
    } catch (Exception $e) {
        jsonError('Error al actualizar etapa: ' . $e->getMessage(), 500);
    }
} else {
    jsonError('Método no permitido', 405);
}

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/proyectos/Proyectos.php';

$user = requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$proyectos = new Proyectos();
$data = $proyectos->obtener($id);
if (!$data) jsonError('Proyecto no encontrado', 404);

$data['etapas'] = $proyectos->getEtapas($id);
$data['indicadores'] = $proyectos->getIndicadores($id);

// Get documents count
$stmt = getDB()->prepare("SELECT COUNT(*) as total FROM documentos WHERE proyecto_id = ?");
$stmt->execute([$id]);
$data['documentos_count'] = (int)$stmt->fetch()['total'];

jsonResponse($data);

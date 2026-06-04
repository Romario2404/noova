<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/documentos/Documentos.php';

$user = requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('ID requerido');

$documentos = new Documentos();
$data = $documentos->obtener($id);
if (!$data) jsonError('Documento no encontrado', 404);

$data['versiones'] = $documentos->getVersiones($id);
jsonResponse($data);

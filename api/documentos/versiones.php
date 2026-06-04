<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/documentos/Documentos.php';

$user = requireAuth();

$documentos = new Documentos();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload new version
    $documentoId = (int)($_POST['documento_id'] ?? 0);
    if (!$documentoId) jsonError('ID de documento requerido');

    if (empty($_FILES['archivo'])) jsonError('Archivo requerido');

    $cambios = $_POST['cambios'] ?? null;
    $result = $documentos->nuevaVersion($documentoId, $_FILES['archivo'], $user['id'], $cambios);

    if ($result === null) jsonError('Error al subir nueva versión', 500);
    jsonResponse(['version' => $result, 'mensaje' => 'Nueva versión subida correctamente'], 201);
} else {
    $documentoId = (int)($_GET['documento_id'] ?? 0);
    if (!$documentoId) jsonError('ID de documento requerido');
    jsonResponse($documentos->getVersiones($documentoId));
}

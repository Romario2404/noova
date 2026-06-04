<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/documentos/Documentos.php';

$user = requireAuth();

$filtros = [];
if (!empty($_GET['proyecto_id'])) $filtros['proyecto_id'] = $_GET['proyecto_id'];
if (!empty($_GET['categoria'])) $filtros['categoria'] = $_GET['categoria'];
if (!empty($_GET['search'])) $filtros['search'] = $_GET['search'];

$documentos = new Documentos();
$data = $documentos->listar($filtros, $user['id'], $user['rol_nombre']);
jsonResponse($data);

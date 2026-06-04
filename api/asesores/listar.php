<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/asesores/Asesores.php';

$user = requireAuth();
$asesores = new Asesores();

$filtros = [];
if (!empty($_GET['search'])) $filtros['search'] = $_GET['search'];
if (!empty($_GET['especialidad'])) $filtros['especialidad'] = $_GET['especialidad'];

$data = $asesores->listar($filtros);
jsonResponse($data);

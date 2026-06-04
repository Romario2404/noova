<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/reportes/Reportes.php';

$user = requireAuth();

$reportes = new Reportes();
$data = $reportes->listar($user['id']);
jsonResponse($data);

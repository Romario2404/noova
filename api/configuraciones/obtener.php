<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/configuraciones/Configuraciones.php';

$config = new Configuraciones();
jsonResponse($config->getSiteInfo());

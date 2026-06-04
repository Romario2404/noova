<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../modules/reportes/Reportes.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$tipo = $input['tipo'] ?? '';
$formato = $input['formato'] ?? 'pdf';

if (!in_array($tipo, ['proyectos', 'clientes', 'indicadores'])) jsonError('Tipo de reporte inválido');
if (!in_array($formato, ['pdf', 'excel', 'word'])) $formato = 'pdf';

try {
    $reportes = new Reportes();

    if ($tipo === 'proyectos') {
        $ruta = $reportes->generarProyectos($input, $formato);
    } elseif ($tipo === 'clientes') {
        $ruta = $reportes->generarClientes($formato);
    } elseif ($tipo === 'indicadores') {
        $ruta = $reportes->generarIndicadores($formato);
    } else {
        $ruta = null;
    }

    if (!$ruta) jsonError('Error al generar el reporte', 500);

    // Save report record
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO reportes (usuario_id, nombre, tipo, formato, parametros, ruta_archivo, estado)
        VALUES (?, ?, ?, ?, ?, ?, 'generado')
    ");
    $stmt->execute([
        $user['id'],
        "Reporte de $tipo",
        $tipo,
        $formato,
        json_encode($input),
        $ruta
    ]);

    logActivity('reporte_generado', 'reportes', null, "Tipo: $tipo, Formato: $formato");

    jsonResponse([
        'ruta' => $ruta,
        'url' => SITE_URL . '/' . $ruta,
        'mensaje' => 'Reporte generado correctamente'
    ], 201);
} catch (Exception $e) {
    jsonError('Error al generar reporte: ' . $e->getMessage(), 500);
}

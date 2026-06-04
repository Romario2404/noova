<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

if (!isset($_FILES['archivo'])) {
    jsonError('No se ha enviado ningún archivo');
}

$proyectoId = $_POST['proyecto_id'] ?? null;
$descripcion = $_POST['descripcion'] ?? '';
$categoria = $_POST['categoria'] ?? 'documento';

try {
    $db = getDB();

    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'dwg' => 'application/cad',
        'dxf' => 'application/cad',
        'zip' => 'application/archive',
        'rar' => 'application/archive',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
    ];
    $tipo = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';

    $allowedExts = ['pdf', 'docx', 'xlsx', 'pptx', 'dwg', 'dxf', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi'];
    if (!in_array($ext, $allowedExts)) {
        jsonError('Tipo de archivo no permitido');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonError('El archivo excede el tamaño máximo permitido (50MB)');
    }

    $uploadDir = UPLOAD_PATH . 'documentos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    $relativePath = 'uploads/documentos/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        jsonError('Error al subir el archivo', 500);
    }

    $codigo = 'DOC-' . strtoupper(substr(uniqid(), -6));

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO documentos (
            proyecto_id, usuario_id, codigo, nombre_original, nombre_archivo,
            ruta, tipo, extension, tamano, descripcion, categoria
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $proyectoId,
        $user['id'],
        $codigo,
        $file['name'],
        $filename,
        $relativePath,
        $tipo,
        $ext,
        $file['size'],
        $descripcion,
        $categoria
    ]);

    $docId = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO documento_versiones (documento_id, version, nombre_archivo, ruta, tamano, usuario_id, cambios)
        VALUES (?, 1, ?, ?, ?, ?, 'Versión inicial')
    ");
    $stmt->execute([$docId, $filename, $relativePath, $file['size'], $user['id']]);

    $db->commit();

    logActivity('subir_documento', 'documento', $docId, "Subida de archivo: {$file['name']}");

    jsonResponse([
        'success' => true,
        'message' => 'Archivo subido exitosamente',
        'id' => $docId,
        'codigo' => $codigo,
        'archivo' => $relativePath
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Error al subir archivo: ' . $e->getMessage(), 500);
}

<?php
require_once __DIR__ . '/../config.php';

$user = requireAuth();
$db = getDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $db->prepare("
        SELECT u.id, u.codigo, u.dni, u.nombres, u.apellidos, u.email, u.telefono, u.celular,
               u.foto, u.cargo, u.empresa, u.direccion, u.ciudad, u.pais, u.theme, u.modo,
               r.nombre as rol_nombre, r.nivel as rol_nivel
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user['id']]);
    jsonResponse($stmt->fetch());
} elseif ($metodo === 'PUT' || $metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $campos = [];
    $params = [];
    foreach (['nombres', 'apellidos', 'telefono', 'celular', 'cargo', 'empresa', 'direccion', 'ciudad', 'pais'] as $campo) {
        if (isset($input[$campo])) {
            $campos[] = "$campo = ?";
            $params[] = $input[$campo];
        }
    }

    if (!empty($input['password'])) {
        $campos[] = "password_hash = ?";
        $params[] = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($campos)) jsonError('No hay datos para actualizar');

    $params[] = $user['id'];
    $stmt = $db->prepare("UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?");
    $stmt->execute($params);

    // Handle photo upload
    if (!empty($_FILES['foto'])) {
        $ruta = uploadFile($_FILES['foto'], 'images');
        if ($ruta) {
            $stmt = $db->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
            $stmt->execute([$ruta, $user['id']]);
        }
    }

    logActivity('perfil_actualizado', 'usuarios', $user['id']);
    jsonResponse(['mensaje' => 'Perfil actualizado correctamente']);
} else {
    jsonError('Método no permitido', 405);
}

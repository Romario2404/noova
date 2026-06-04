<?php
require_once __DIR__ . '/../config.php';

$user = requireRole('admin');
$db = getDB();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $db->query("
        SELECT u.*, r.nombre as rol_nombre, r.nivel as rol_nivel
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        ORDER BY u.created_at DESC
    ");
    $usuarios = $stmt->fetchAll();

    // Remove password hashes
    foreach ($usuarios as &$u) unset($u['password_hash']);

    jsonResponse($usuarios);
} elseif ($metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $error = validateInput($input, [
        'nombres' => 'required',
        'apellidos' => 'required',
        'email' => 'required|email',
        'password' => 'required',
        'rol_id' => 'required|numeric'
    ]);
    if ($error) jsonError($error);

    // Check duplicate email
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) jsonError('El email ya está registrado');

    $stmt = $db->prepare("
        INSERT INTO usuarios (rol_id, codigo, nombres, apellidos, email, password_hash, telefono, cargo, empresa, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $codigo = 'USR-' . str_pad(time(), 8, '0', STR_PAD_LEFT);
    $stmt->execute([
        $input['rol_id'],
        $codigo,
        $input['nombres'],
        $input['apellidos'],
        $input['email'],
        password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        $input['telefono'] ?? null,
        $input['cargo'] ?? null,
        $input['empresa'] ?? null,
        $input['activo'] ?? 1
    ]);

    logActivity('usuario_creado', 'usuarios', (int)$db->lastInsertId());
    jsonResponse(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Usuario creado correctamente'], 201);
} elseif ($metodo === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
    if (!$id) jsonError('ID requerido');

    $campos = [];
    $params = [];
    foreach (['nombres', 'apellidos', 'telefono', 'cargo', 'empresa', 'activo', 'rol_id'] as $campo) {
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

    $params[] = $id;
    $stmt = $db->prepare("UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?");
    $stmt->execute($params);
    logActivity('usuario_actualizado', 'usuarios', $id);
    jsonResponse(['mensaje' => 'Usuario actualizado correctamente']);
} else {
    jsonError('Método no permitido', 405);
}

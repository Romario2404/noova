<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$error = validateInput($data, [
    'nombres' => 'required',
    'apellidos' => 'required',
    'email' => 'required|email',
    'password' => 'required',
    'rol' => 'required'
]);

if ($error) {
    jsonError($error);
}

if (strlen($data['password']) < 8) {
    jsonError('La contraseña debe tener al menos 8 caracteres');
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        jsonError('El email ya está registrado', 409);
    }

    $stmt = $db->prepare("SELECT id FROM roles WHERE nombre = ?");
    $stmt->execute([$data['rol']]);
    $role = $stmt->fetch();
    if (!$role) {
        jsonError('Rol inválido');
    }

    $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $codigo = 'USR-' . strtoupper(substr(uniqid(), -6));

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO usuarios (rol_id, codigo, nombres, apellidos, email, password_hash, telefono, empresa, cargo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $role['id'],
        $codigo,
        $data['nombres'],
        $data['apellidos'],
        $data['email'],
        $passwordHash,
        $data['telefono'] ?? null,
        $data['empresa'] ?? null,
        $data['cargo'] ?? null
    ]);

    $userId = $db->lastInsertId();

    if ($data['rol'] === 'cliente' && isset($data['razon_social'])) {
        $stmt = $db->prepare("
            INSERT INTO clientes (usuario_id, codigo, razon_social, ruc, rubro)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            'CLI-' . strtoupper(substr(uniqid(), -6)),
            $data['razon_social'],
            $data['ruc'] ?? null,
            $data['rubro'] ?? null
        ]);
    }

    if ($data['rol'] === 'asesor' && isset($data['especialidad'])) {
        $stmt = $db->prepare("
            INSERT INTO asesores (usuario_id, codigo, especialidad, experiencia_anos)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            'ASE-' . strtoupper(substr(uniqid(), -6)),
            $data['especialidad'],
            $data['experiencia_anos'] ?? 0
        ]);
    }

    $db->commit();

    logActivity('register', 'usuario', $userId, "Registro de usuario: {$data['email']}");

    jsonResponse([
        'success' => true,
        'message' => 'Registro exitoso'
    ], 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    jsonError('Error al registrar: ' . $e->getMessage(), 500);
}

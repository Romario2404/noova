<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$error = validateInput($data, [
    'email' => 'required|email',
    'password' => 'required'
]);

if ($error) {
    jsonError($error);
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.nombre as rol_nombre, r.nivel as rol_nivel
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE u.email = ? AND u.activo = 1
    ");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        logActivity('login_failed', 'usuario', null, "Intento de login fallido para: {$data['email']}");
        jsonError('Credenciales inválidas', 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    $token = bin2hex(random_bytes(64));
    $stmt = $db->prepare("
        INSERT INTO sessions (usuario_id, token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ");
    $stmt->execute([$user['id'], $token, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

    logActivity('login', 'usuario', $user['id'], "Inicio de sesión exitoso");

    unset($user['password_hash']);
    $user['token'] = $token;
    $user['csrf'] = $_SESSION['csrf_token'];

    jsonResponse([
        'success' => true,
        'user' => $user,
        'message' => 'Inicio de sesión exitoso'
    ]);

} catch (Exception $e) {
    jsonError('Error al iniciar sesión', 500);
}

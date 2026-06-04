<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$data = $_POST;

$error = validateInput($data, [
    'nombres' => 'required',
    'apellidos' => 'required',
    'email' => 'required|email',
    'mensaje' => 'required'
]);

if ($error) {
    jsonError($error);
}

try {
    $db = getDB();

    $telefono = isset($data['telefono']) ? $data['telefono'] : 'No especificado';
    $empresa = isset($data['empresa']) ? $data['empresa'] : 'No especificada';
    $servicio = isset($data['servicio']) ? $data['servicio'] : 'No especificado';

    $asunto = "Contacto desde web - {$data['nombres']} {$data['apellidos']}";
    $cuerpo = "Nombre: {$data['nombres']} {$data['apellidos']}\n"
            . "Email: {$data['email']}\n"
            . "Teléfono: {$telefono}\n"
            . "Empresa: {$empresa}\n"
            . "Servicio: {$servicio}\n\n"
            . "Mensaje:\n{$data['mensaje']}";

    $stmt = $db->prepare("
        INSERT INTO mensajes (remitente_id, asunto, cuerpo)
        VALUES (NULL, ?, ?)
    ");
    $stmt->execute([$asunto, $cuerpo]);

    $mensajeId = $db->lastInsertId();

    $admins = $db->query("SELECT id FROM usuarios WHERE rol_id = (SELECT id FROM roles WHERE nombre = 'admin')");
    while ($admin = $admins->fetch()) {
        $stmt = $db->prepare("INSERT INTO mensaje_destinatarios (mensaje_id, destinatario_id) VALUES (?, ?)");
        $stmt->execute([$mensajeId, $admin['id']]);
    }

    // Send email notification
    $to = 'contacto@noova.pe';
    $subject = "Nuevo contacto web - {$data['nombres']} {$data['apellidos']}";
    $headers = "From: {$data['email']}\r\n";
    $headers .= "Reply-To: {$data['email']}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $htmlBody = "
        <h2>Nuevo mensaje desde la web</h2>
        <p><strong>Nombre:</strong> {$data['nombres']} {$data['apellidos']}</p>
        <p><strong>Email:</strong> {$data['email']}</p>
        <p><strong>Teléfono:</strong> {$telefono}</p>
        <p><strong>Empresa:</strong> {$empresa}</p>
        <p><strong>Servicio:</strong> {$servicio}</p>
        <hr>
        <p><strong>Mensaje:</strong></p>
        <p>" . nl2br($data['mensaje']) . "</p>
    ";

    @mail($to, $subject, $htmlBody, $headers);

    jsonResponse([
        'success' => true,
        'message' => 'Mensaje enviado con éxito. Nos comunicaremos pronto.'
    ]);

} catch (Exception $e) {
    jsonError('Error al enviar mensaje: ' . $e->getMessage(), 500);
}

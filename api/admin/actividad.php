<?php
require_once __DIR__ . '/../config.php';

$user = requireRole('admin', 'supervisor');
$db = getDB();

$stmt = $db->query("
    SELECT al.*, u.nombres, u.apellidos, u.foto
    FROM activity_logs al
    LEFT JOIN usuarios u ON al.usuario_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 100
");
jsonResponse($stmt->fetchAll());

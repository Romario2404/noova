<?php
class Notificaciones {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function listar(int $userId, bool $soloNoLeidas = false): array {
        $sql = "SELECT * FROM notificaciones WHERE usuario_id = ?";
        $params = [$userId];

        if ($soloNoLeidas) {
            $sql .= " AND leido = 0";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function contarNoLeidas(int $userId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total FROM notificaciones
            WHERE usuario_id = ? AND leido = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['total'];
    }

    public function crear(int $userId, string $tipo, string $titulo, ?string $mensaje = null,
                          ?string $refTipo = null, ?int $refId = null): int {
        $stmt = $this->db->prepare("
            INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_tipo, referencia_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $tipo, $titulo, $mensaje, $refTipo, $refId]);
        return (int)$this->db->lastInsertId();
    }

    public function crearParaUsuarios(array $userIds, string $tipo, string $titulo,
                                       ?string $mensaje = null, ?string $refTipo = null, ?int $refId = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, referencia_tipo, referencia_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($userIds as $uid) {
            $stmt->execute([$uid, $tipo, $titulo, $mensaje, $refTipo, $refId]);
        }
    }

    public function notificarNuevoProyecto(int $proyectoId, string $nombre): void {
        $admins = $this->db->query("
            SELECT u.id FROM usuarios u JOIN roles r ON u.rol_id = r.id
            WHERE r.nivel >= 80 AND u.activo = 1
        ")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($admins)) {
            $this->crearParaUsuarios($admins, 'nuevo_proyecto',
                "Nuevo proyecto: $nombre",
                "Se ha creado un nuevo proyecto en el sistema.",
                'proyectos', $proyectoId);
        }
    }

    public function notificarDocumentoSubido(int $documentoId, string $nombre, int $proyectoId): void {
        $participantes = $this->db->prepare("
            SELECT DISTINCT u.id FROM usuarios u
            LEFT JOIN proyectos p ON (p.cliente_id IN (SELECT id FROM clientes WHERE usuario_id = u.id) OR p.asesor_id IN (SELECT id FROM asesores WHERE usuario_id = u.id) OR p.supervisor_id = u.id)
            JOIN roles r ON u.rol_id = r.id
            WHERE p.id = ? AND u.activo = 1
        ");
        $participantes->execute([$proyectoId]);
        $userIds = $participantes->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($userIds)) {
            $this->crearParaUsuarios($userIds, 'nuevo_documento',
                "Nuevo documento: $nombre",
                "Se ha subido un nuevo documento al proyecto.",
                'documentos', $documentoId);
        }
    }

    public function marcarLeida(int $id, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE notificaciones SET leido = 1, leido_at = NOW()
            WHERE id = ? AND usuario_id = ?
        ");
        return $stmt->execute([$id, $userId]);
    }

    public function marcarTodasLeidas(int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE notificaciones SET leido = 1, leido_at = NOW()
            WHERE usuario_id = ? AND leido = 0
        ");
        return $stmt->execute([$userId]);
    }

    public function eliminar(int $id, int $userId): bool {
        $stmt = $this->db->prepare("DELETE FROM notificaciones WHERE id = ? AND usuario_id = ?");
        return $stmt->execute([$id, $userId]);
    }
}

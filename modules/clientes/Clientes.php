<?php
class Clientes {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function listar(array $filtros = []): array {
        $sql = "SELECT c.*, u.nombres, u.apellidos, u.email, u.telefono, u.celular,
                       (SELECT COUNT(*) FROM proyectos WHERE cliente_id = c.id AND activo = 1) as proyectos_activos,
                       (SELECT COUNT(*) FROM proyectos WHERE cliente_id = c.id) as total_proyectos
                FROM clientes c
                LEFT JOIN usuarios u ON c.usuario_id = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['search'])) {
            $sql .= " AND (c.razon_social LIKE ? OR c.ruc LIKE ? OR u.nombres LIKE ? OR u.apellidos LIKE ?)";
            $s = "%{$filtros['search']}%";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        if (!empty($filtros['rubro'])) {
            $sql .= " AND c.rubro = ?";
            $params[] = $filtros['rubro'];
        }

        $sql .= " ORDER BY c.razon_social ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, u.nombres, u.apellidos, u.email, u.telefono, u.celular, u.direccion, u.ciudad, u.pais
            FROM clientes c
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): int {
        $this->db->beginTransaction();
        try {
            $codigo = $this->generarCodigo();
            $stmt = $this->db->prepare("
                INSERT INTO clientes (codigo, razon_social, ruc, rubro, sitio_web, notas)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codigo,
                $data['razon_social'],
                $data['ruc'] ?? null,
                $data['rubro'] ?? null,
                $data['sitio_web'] ?? null,
                $data['notas'] ?? null
            ]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            logActivity('cliente_creado', 'clientes', $id, "Cliente: {$data['razon_social']}");
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): bool {
        $campos = [];
        $params = [];
        foreach (['razon_social', 'ruc', 'rubro', 'sitio_web', 'notas'] as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                $params[] = $data[$campo];
            }
        }
        if (empty($campos)) return false;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE clientes SET " . implode(', ', $campos) . " WHERE id = ?");
        $result = $stmt->execute($params);
        if ($result) logActivity('cliente_actualizado', 'clientes', $id);
        return $result;
    }

    public function eliminar(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM clientes WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result) logActivity('cliente_eliminado', 'clientes', $id);
        return $result;
    }

    public function asignarAsesor(int $clienteId, int $asesorId): bool {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO cliente_asesor (cliente_id, asesor_id) VALUES (?, ?)
        ");
        $result = $stmt->execute([$clienteId, $asesorId]);
        if ($result) logActivity('asesor_asignado', 'cliente_asesor', null, "Cliente:$clienteId Asesor:$asesorId");
        return $result;
    }

    public function getAsesores(int $clienteId): array {
        $stmt = $this->db->prepare("
            SELECT a.*, u.nombres, u.apellidos, u.email, u.foto
            FROM asesores a
            JOIN cliente_asesor ca ON a.id = ca.asesor_id
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE ca.cliente_id = ? AND ca.activo = 1
        ");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    private function generarCodigo(): string {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM clientes");
        $count = $stmt->fetch()['total'] + 1;
        return 'CLI-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

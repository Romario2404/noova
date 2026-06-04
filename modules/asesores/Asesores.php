<?php
class Asesores {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function listar(array $filtros = []): array {
        $sql = "SELECT a.*, u.nombres, u.apellidos, u.email, u.telefono, u.celular, u.foto, u.cargo,
                       (SELECT COUNT(*) FROM cliente_asesor WHERE asesor_id = a.id AND activo = 1) as total_clientes,
                       (SELECT COUNT(*) FROM proyectos WHERE asesor_id = a.id AND activo = 1) as proyectos_activos
                FROM asesores a
                LEFT JOIN usuarios u ON a.usuario_id = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['search'])) {
            $sql .= " AND (u.nombres LIKE ? OR u.apellidos LIKE ? OR a.especialidad LIKE ?)";
            $s = "%{$filtros['search']}%";
            $params = array_merge($params, [$s, $s, $s]);
        }
        if (!empty($filtros['especialidad'])) {
            $sql .= " AND a.especialidad LIKE ?";
            $params[] = "%{$filtros['especialidad']}%";
        }

        $sql .= " ORDER BY u.apellidos ASC, u.nombres ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT a.*, u.nombres, u.apellidos, u.email, u.telefono, u.celular, u.foto, u.cargo, u.direccion
            FROM asesores a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): int {
        $this->db->beginTransaction();
        try {
            $codigo = $this->generarCodigo();
            $stmt = $this->db->prepare("
                INSERT INTO asesores (codigo, especialidad, experiencia_anos, curriculum)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $codigo,
                $data['especialidad'] ?? null,
                $data['experiencia_anos'] ?? null,
                $data['curriculum'] ?? null
            ]);
            $id = (int)$this->db->lastInsertId();
            if (!empty($data['usuario_id'])) {
                $stmt = $this->db->prepare("UPDATE asesores SET usuario_id = ? WHERE id = ?");
                $stmt->execute([$data['usuario_id'], $id]);
            }
            $this->db->commit();
            logActivity('asesor_creado', 'asesores', $id);
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): bool {
        $campos = [];
        $params = [];
        foreach (['especialidad', 'experiencia_anos', 'curriculum'] as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                $params[] = $data[$campo];
            }
        }
        if (empty($campos)) return false;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE asesores SET " . implode(', ', $campos) . " WHERE id = ?");
        $result = $stmt->execute($params);
        if ($result) logActivity('asesor_actualizado', 'asesores', $id);
        return $result;
    }

    public function eliminar(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM asesores WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result) logActivity('asesor_eliminado', 'asesores', $id);
        return $result;
    }

    public function getClientes(int $asesorId): array {
        $stmt = $this->db->prepare("
            SELECT c.*, u.nombres, u.apellidos, u.email, u.telefono,
                   (SELECT COUNT(*) FROM proyectos WHERE cliente_id = c.id AND activo = 1) as proyectos_activos
            FROM clientes c
            JOIN cliente_asesor ca ON c.id = ca.cliente_id
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            WHERE ca.asesor_id = ? AND ca.activo = 1
            ORDER BY c.razon_social ASC
        ");
        $stmt->execute([$asesorId]);
        return $stmt->fetchAll();
    }

    public function getProximosVencimientos(int $asesorId, int $dias = 30): array {
        $stmt = $this->db->prepare("
            SELECT p.id, p.codigo, p.nombre, p.fecha_fin_estimada, p.porcentaje_avance,
                   c.razon_social as cliente
            FROM proyectos p
            JOIN cliente_asesor ca ON p.cliente_id = ca.cliente_id
            LEFT JOIN clientes c ON p.cliente_id = c.id
            WHERE ca.asesor_id = ? AND ca.activo = 1
              AND p.activo = 1 AND p.estado IN ('planificacion', 'en_progreso')
              AND p.fecha_fin_estimada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY p.fecha_fin_estimada ASC
        ");
        $stmt->execute([$asesorId, $dias]);
        return $stmt->fetchAll();
    }

    private function generarCodigo(): string {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM asesores");
        $count = $stmt->fetch()['total'] + 1;
        return 'ASE-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

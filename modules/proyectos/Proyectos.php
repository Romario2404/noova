<?php
class Proyectos {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function listar(array $filtros = [], ?int $userId = null, ?string $rol = null): array {
        $sql = "SELECT p.*, c.razon_social as cliente_nombre,
                       u_ase.nombres as asesor_nombres, u_ase.apellidos as asesor_apellidos,
                       u_sup.nombres as supervisor_nombres, u_sup.apellidos as supervisor_apellidos
                FROM proyectos p
                LEFT JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN asesores a ON p.asesor_id = a.id
                LEFT JOIN usuarios u_ase ON a.usuario_id = u_ase.id
                LEFT JOIN usuarios u_sup ON p.supervisor_id = u_sup.id
                WHERE p.activo = 1";
        $params = [];

        if ($rol === 'cliente' && $userId) {
            $sql .= " AND p.cliente_id IN (SELECT id FROM clientes WHERE usuario_id = ?)";
            $params[] = $userId;
        } elseif ($rol === 'asesor' && $userId) {
            $sql .= " AND p.asesor_id IN (SELECT id FROM asesores WHERE usuario_id = ?)";
            $params[] = $userId;
        }

        if (!empty($filtros['search'])) {
            $sql .= " AND (p.codigo LIKE ? OR p.nombre LIKE ? OR c.razon_social LIKE ?)";
            $s = "%{$filtros['search']}%";
            $params = array_merge($params, [$s, $s, $s]);
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND p.estado = ?";
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= " AND p.tipo = ?";
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['prioridad'])) {
            $sql .= " AND p.prioridad = ?";
            $params[] = $filtros['prioridad'];
        }
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND p.cliente_id = ?";
            $params[] = $filtros['cliente_id'];
        }
        if (!empty($filtros['asesor_id'])) {
            $sql .= " AND p.asesor_id = ?";
            $params[] = $filtros['asesor_id'];
        }

        $sql .= " ORDER BY p.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, c.razon_social as cliente_nombre, c.ruc as cliente_ruc,
                   u_ase.nombres as asesor_nombres, u_ase.apellidos as asesor_apellidos, u_ase.email as asesor_email,
                   u_sup.nombres as supervisor_nombres, u_sup.apellidos as supervisor_apellidos,
                   (SELECT COUNT(*) FROM proyecto_etapas WHERE proyecto_id = p.id) as total_etapas,
                   (SELECT COUNT(*) FROM proyecto_etapas WHERE proyecto_id = p.id AND estado = 'completado') as etapas_completadas,
                   (SELECT COUNT(*) FROM documentos WHERE proyecto_id = p.id) as total_documentos
            FROM proyectos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN asesores a ON p.asesor_id = a.id
            LEFT JOIN usuarios u_ase ON a.usuario_id = u_ase.id
            LEFT JOIN usuarios u_sup ON p.supervisor_id = u_sup.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $data): int {
        $this->db->beginTransaction();
        try {
            $codigo = $this->generarCodigo();
            $stmt = $this->db->prepare("
                INSERT INTO proyectos (codigo, nombre, descripcion, cliente_id, asesor_id, supervisor_id,
                    estado, prioridad, tipo, fecha_inicio, fecha_fin_estimada, presupuesto, ubicacion, notas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codigo,
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['cliente_id'] ?? null,
                $data['asesor_id'] ?? null,
                $data['supervisor_id'] ?? null,
                $data['estado'] ?? 'planificacion',
                $data['prioridad'] ?? 'media',
                $data['tipo'] ?? 'otro',
                $data['fecha_inicio'] ?? null,
                $data['fecha_fin_estimada'] ?? null,
                $data['presupuesto'] ?? null,
                $data['ubicacion'] ?? null,
                $data['notas'] ?? null
            ]);
            $id = (int)$this->db->lastInsertId();

            // Create default stages
            $etapas = [
                ['Diagnóstico', 1],
                ['Diseño', 2],
                ['Ejecución', 3],
                ['Monitoreo', 4],
                ['Entrega', 5]
            ];
            $stmtEtapa = $this->db->prepare("
                INSERT INTO proyecto_etapas (proyecto_id, nombre, orden) VALUES (?, ?, ?)
            ");
            foreach ($etapas as $etapa) {
                $stmtEtapa->execute([$id, $etapa[0], $etapa[1]]);
            }

            $this->db->commit();
            logActivity('proyecto_creado', 'proyectos', $id, "Proyecto: {$data['nombre']}");
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): bool {
        $campos = [];
        $params = [];
        $permitidos = ['nombre', 'descripcion', 'cliente_id', 'asesor_id', 'supervisor_id',
                        'estado', 'prioridad', 'tipo', 'fecha_inicio', 'fecha_fin_estimada',
                        'fecha_fin_real', 'presupuesto', 'porcentaje_avance', 'ubicacion', 'notas'];
        foreach ($permitidos as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                $params[] = $data[$campo];
            }
        }
        if (empty($campos)) return false;
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE proyectos SET " . implode(', ', $campos) . " WHERE id = ?");
        $result = $stmt->execute($params);
        if ($result) logActivity('proyecto_actualizado', 'proyectos', $id);
        return $result;
    }

    public function eliminar(int $id): bool {
        $stmt = $this->db->prepare("UPDATE proyectos SET activo = 0 WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result) logActivity('proyecto_eliminado', 'proyectos', $id);
        return $result;
    }

    public function getEtapas(int $proyectoId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM proyecto_etapas WHERE proyecto_id = ? ORDER BY orden ASC
        ");
        $stmt->execute([$proyectoId]);
        return $stmt->fetchAll();
    }

    public function actualizarEtapa(int $etapaId, array $data): bool {
        $campos = [];
        $params = [];
        foreach (['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'porcentaje_completado', 'estado'] as $campo) {
            if (isset($data[$campo])) {
                $campos[] = "$campo = ?";
                $params[] = $data[$campo];
            }
        }
        if (empty($campos)) return false;
        $params[] = $etapaId;
        $stmt = $this->db->prepare("UPDATE proyecto_etapas SET " . implode(', ', $campos) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    public function getIndicadores(int $proyectoId): array {
        $stmt = $this->db->prepare("SELECT * FROM proyecto_indicadores WHERE proyecto_id = ?");
        $stmt->execute([$proyectoId]);
        return $stmt->fetchAll();
    }

    public function getEstadisticas(): array {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'planificacion' THEN 1 ELSE 0 END) as planificacion,
                SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completado,
                SUM(CASE WHEN estado IN ('suspendido','cancelado') THEN 1 ELSE 0 END) as suspendido,
                COALESCE(AVG(porcentaje_avance), 0) as avance_promedio
            FROM proyectos WHERE activo = 1
        ");
        return $stmt->fetch();
    }

    private function generarCodigo(): string {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM proyectos");
        $count = $stmt->fetch()['total'] + 1;
        return 'PROJ-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

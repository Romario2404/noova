<?php
class Documentos {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function listar(array $filtros = [], ?int $userId = null, ?string $rol = null): array {
        $sql = "SELECT d.*, u.nombres, u.apellidos, p.nombre as proyecto_nombre
                FROM documentos d
                LEFT JOIN usuarios u ON d.usuario_id = u.id
                LEFT JOIN proyectos p ON d.proyecto_id = p.id
                WHERE 1=1";
        $params = [];

        if ($rol === 'cliente' && $userId) {
            $sql .= " AND (d.publico = 1 OR d.proyecto_id IN (
                SELECT p.id FROM proyectos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE c.usuario_id = ?
            ))";
            $params[] = $userId;
        }

        if (!empty($filtros['proyecto_id'])) {
            $sql .= " AND d.proyecto_id = ?";
            $params[] = $filtros['proyecto_id'];
        }
        if (!empty($filtros['categoria'])) {
            $sql .= " AND d.categoria = ?";
            $params[] = $filtros['categoria'];
        }
        if (!empty($filtros['search'])) {
            $sql .= " AND (d.nombre_original LIKE ? OR d.descripcion LIKE ?)";
            $s = "%{$filtros['search']}%";
            $params = array_merge($params, [$s, $s]);
        }

        $sql .= " ORDER BY d.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT d.*, u.nombres, u.apellidos, p.nombre as proyecto_nombre
            FROM documentos d
            LEFT JOIN usuarios u ON d.usuario_id = u.id
            LEFT JOIN proyectos p ON d.proyecto_id = p.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function subir(array $file, array $data, int $userId): ?int {
        $ruta = uploadFile($file, 'documentos');
        if (!$ruta) return null;

        $this->db->beginTransaction();
        try {
            $codigo = $this->generarCodigo();
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $stmt = $this->db->prepare("
                INSERT INTO documentos (proyecto_id, usuario_id, codigo, nombre_original, nombre_archivo,
                    ruta, tipo, extension, tamano, descripcion, categoria, publico)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['proyecto_id'] ?? null,
                $userId,
                $codigo,
                $file['name'],
                basename($ruta),
                $ruta,
                mime_content_type(__DIR__ . '/../../' . $ruta) ?: 'application/octet-stream',
                $ext,
                $file['size'],
                $data['descripcion'] ?? null,
                $data['categoria'] ?? 'documento',
                !empty($data['publico']) ? 1 : 0
            ]);
            $id = (int)$this->db->lastInsertId();

            // Create initial version
            $stmt = $this->db->prepare("
                INSERT INTO documento_versiones (documento_id, version, nombre_archivo, ruta, tamano, usuario_id, cambios)
                VALUES (?, 1, ?, ?, ?, ?, 'Versión inicial')
            ");
            $stmt->execute([$id, $file['name'], $ruta, $file['size'], $userId]);

            $this->db->commit();
            logActivity('documento_subido', 'documentos', $id, "Documento: {$file['name']}");
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function nuevaVersion(int $documentoId, array $file, int $userId, ?string $cambios = null): ?int {
        $ruta = uploadFile($file, 'documentos');
        if (!$ruta) return null;

        $doc = $this->obtener($documentoId);
        if (!$doc) return null;

        $nuevaVersion = $doc['version'] + 1;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO documento_versiones (documento_id, version, nombre_archivo, ruta, tamano, usuario_id, cambios)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$documentoId, $nuevaVersion, $file['name'], $ruta, $file['size'], $userId, $cambios]);

            $stmt = $this->db->prepare("
                UPDATE documentos SET nombre_archivo = ?, ruta = ?, tamano = ?, version = ? WHERE id = ?
            ");
            $stmt->execute([$file['name'], $ruta, $file['size'], $nuevaVersion, $documentoId]);

            $this->db->commit();
            logActivity('documento_version', 'documentos', $documentoId, "Nueva versión: $nuevaVersion");
            return $nuevaVersion;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getVersiones(int $documentoId): array {
        $stmt = $this->db->prepare("
            SELECT dv.*, u.nombres, u.apellidos
            FROM documento_versiones dv
            LEFT JOIN usuarios u ON dv.usuario_id = u.id
            WHERE dv.documento_id = ?
            ORDER BY dv.version DESC
        ");
        $stmt->execute([$documentoId]);
        return $stmt->fetchAll();
    }

    public function eliminar(int $id): bool {
        $doc = $this->obtener($id);
        if (!$doc) return false;

        $ruta = __DIR__ . '/../../' . $doc['ruta'];
        if (file_exists($ruta)) unlink($ruta);

        $stmt = $this->db->prepare("DELETE FROM documentos WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result) logActivity('documento_eliminado', 'documentos', $id);
        return $result;
    }

    public function getCategorias(): array {
        return ['informe', 'plano', 'contrato', 'foto', 'video', 'modelo3d', 'reporte', 'documento', 'otro'];
    }

    private function generarCodigo(): string {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM documentos");
        $count = $stmt->fetch()['total'] + 1;
        return 'DOC-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

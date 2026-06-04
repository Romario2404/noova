<?php
class Configuraciones {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function obtener(string $clave): ?string {
        $stmt = $this->db->prepare("SELECT valor FROM configuraciones WHERE clave = ?");
        $stmt->execute([$clave]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : null;
    }

    public function obtenerTodas(): array {
        $stmt = $this->db->query("SELECT * FROM configuraciones ORDER BY clave ASC");
        return $stmt->fetchAll();
    }

    public function actualizar(string $clave, string $valor): bool {
        $stmt = $this->db->prepare("
            INSERT INTO configuraciones (clave, valor) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");
        $result = $stmt->execute([$clave, $valor]);
        if ($result) logActivity('config_actualizada', 'configuraciones', null, "Clave: $clave");
        return $result;
    }

    public function actualizarMultiples(array $data): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO configuraciones (clave, valor) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)
            ");
            foreach ($data as $clave => $valor) {
                $stmt->execute([$clave, $valor]);
            }
            $this->db->commit();
            logActivity('configuraciones_actualizadas', 'configuraciones');
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getSiteInfo(): array {
        return [
            'site_name' => $this->obtener('site_name') ?? 'NOOVA S.A.C.',
            'site_description' => $this->obtener('site_description') ?? '',
            'contact_email' => $this->obtener('contact_email') ?? 'contacto@noova.pe',
            'contact_phone' => $this->obtener('contact_phone') ?? '+51 1 234 5678',
            'contact_whatsapp' => $this->obtener('contact_whatsapp') ?? '+51999888777',
            'address' => $this->obtener('address') ?? '',
            'default_theme' => $this->obtener('default_theme') ?? 'azul-corporativo',
            'default_mode' => $this->obtener('default_mode') ?? 'light',
        ];
    }

    public function getTemas(): array {
        return [
            'azul-corporativo' => 'Azul Corporativo',
            'celeste-profesional' => 'Celeste Profesional',
            'verde-ejecutivo' => 'Verde Ejecutivo',
            'turquesa-moderno' => 'Turquesa Moderno',
            'morado-corporativo' => 'Morado Corporativo',
            'azul-marino' => 'Azul Marino',
            'gris-ejecutivo' => 'Gris Ejecutivo',
            'arena-profesional' => 'Arena Profesional',
            'cian-tecnologico' => 'Cian Tecnológico',
            'esmeralda' => 'Esmeralda',
            'indigo' => 'Índigo',
            'grafito-claro' => 'Grafito Claro',
            'industrial-moderno' => 'Industrial Moderno',
            'premium-corporativo' => 'Premium Corporativo',
            'empresarial-claro' => 'Empresarial Claro',
        ];
    }
}

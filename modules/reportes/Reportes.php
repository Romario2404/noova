<?php
class Reportes {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function listar(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM reportes WHERE usuario_id = ? ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function generarProyectos(array $filtros = [], string $formato = 'pdf'): ?string {
        $proyectos = $this->db->query("
            SELECT p.*, c.razon_social as cliente,
                   u.nombres as asesor_nombres, u.apellidos as asesor_apellidos
            FROM proyectos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN asesores a ON p.asesor_id = a.id
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE p.activo = 1
            ORDER BY p.created_at DESC
        ")->fetchAll();

        $html = $this->renderHTML('proyectos', $proyectos);
        return $this->exportar($html, $formato, 'reporte_proyectos');
    }

    public function generarClientes(string $formato = 'pdf'): ?string {
        $clientes = $this->db->query("
            SELECT c.*, u.nombres, u.apellidos, u.email, u.telefono,
                   (SELECT COUNT(*) FROM proyectos WHERE cliente_id = c.id) as total_proyectos
            FROM clientes c
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            ORDER BY c.razon_social ASC
        ")->fetchAll();

        $html = $this->renderHTML('clientes', $clientes);
        return $this->exportar($html, $formato, 'reporte_clientes');
    }

    public function generarIndicadores(string $formato = 'pdf'): ?string {
        $stats = $this->db->query("
            SELECT
                (SELECT COUNT(*) FROM proyectos WHERE activo = 1) as total_proyectos,
                (SELECT COUNT(*) FROM clientes) as total_clientes,
                (SELECT COUNT(*) FROM asesores) as total_asesores,
                (SELECT COUNT(*) FROM documentos) as total_documentos,
                (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
                (SELECT COALESCE(AVG(porcentaje_avance), 0) FROM proyectos WHERE activo = 1) as avance_promedio
        ")->fetch();

        $html = $this->renderHTML('indicadores', $stats);
        return $this->exportar($html, $formato, 'reporte_indicadores');
    }

    private function renderHTML(string $tipo, $data): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html><head>
        <meta charset="utf-8">
        <title>NOOVA S.A.C. - Reporte</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; margin: 40px; color: #333; }
            h1 { color: #0a4b7a; border-bottom: 3px solid #0a4b7a; padding-bottom: 10px; }
            h2 { color: #1a7bc4; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #0a4b7a; color: white; }
            tr:hover { background: #f5f8fb; }
            .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .header { text-align: center; margin-bottom: 30px; }
            .footer { margin-top: 40px; text-align: center; color: #888; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
            .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 20px 0; }
            .stat-card { background: #f0f4f8; padding: 20px; border-radius: 8px; text-align: center; }
            .stat-value { font-size: 28px; font-weight: 700; color: #0a4b7a; }
            .stat-label { font-size: 13px; color: #666; margin-top: 4px; }
        </style>
        </head><body>
        <div class="header">
            <h1>NOOVA S.A.C.</h1>
            <p>Reporte Generado: <?= date('d/m/Y H:i') ?></p>
        </div>

        <?php if ($tipo === 'proyectos'): ?>
            <h2>Reporte de Proyectos</h2>
            <table>
                <tr><th>Código</th><th>Nombre</th><th>Cliente</th><th>Asesor</th><th>Estado</th><th>Avance</th></tr>
                <?php foreach ($data as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['codigo']) ?></td>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= htmlspecialchars($p['cliente'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(($p['asesor_nombres'] ?? '') . ' ' . ($p['asesor_apellidos'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($p['estado']) ?></td>
                    <td><?= $p['porcentaje_avance'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </table>

        <?php elseif ($tipo === 'clientes'): ?>
            <h2>Reporte de Clientes</h2>
            <table>
                <tr><th>RUC</th><th>Razón Social</th><th>Contacto</th><th>Email</th><th>Proyectos</th></tr>
                <?php foreach ($data as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['ruc'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['razon_social']) ?></td>
                    <td><?= htmlspecialchars(($c['nombres'] ?? '') . ' ' . ($c['apellidos'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                    <td><?= $c['total_proyectos'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

        <?php elseif ($tipo === 'indicadores'): ?>
            <h2>Indicadores Generales</h2>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?= $data['total_proyectos'] ?></div><div class="stat-label">Total Proyectos</div></div>
                <div class="stat-card"><div class="stat-value"><?= $data['total_clientes'] ?></div><div class="stat-label">Total Clientes</div></div>
                <div class="stat-card"><div class="stat-value"><?= $data['total_asesores'] ?></div><div class="stat-label">Total Asesores</div></div>
                <div class="stat-card"><div class="stat-value"><?= $data['total_documentos'] ?></div><div class="stat-label">Total Documentos</div></div>
                <div class="stat-card"><div class="stat-value"><?= $data['total_usuarios'] ?></div><div class="stat-label">Usuarios Activos</div></div>
                <div class="stat-card"><div class="stat-value"><?= number_format($data['avance_promedio'], 1) ?>%</div><div class="stat-label">Avance Promedio</div></div>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>NOOVA S.A.C. - Consultoría Minera & Ingeniería de Proyectos</p>
            <p>Av. Principal 1234, San Isidro, Lima, Perú | contacto@noova.pe | +51 1 234 5678</p>
        </div>
        </body></html>
        <?php
        return ob_get_clean();
    }

    private function exportar(string $html, string $formato, string $nombre): ?string {
        $dir = __DIR__ . '/../../storage/reportes/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = $nombre . '_' . date('Ymd_His') . '.' . $formato;
        $filepath = $dir . $filename;

        switch ($formato) {
            case 'pdf':
                $this->htmlToPDF($html, $filepath);
                break;
            case 'excel':
                $this->htmlToExcel($html, $filepath);
                break;
            case 'word':
                file_put_contents($filepath, $html);
                break;
            default:
                return null;
        }

        if (file_exists($filepath)) {
            return 'storage/reportes/' . $filename;
        }
        return null;
    }

    private function htmlToPDF(string $html, string $filepath): void {
        // Try multiple PDF generation methods
        if ($this->tryTCPDF($html, $filepath)) return;
        if ($this->tryMPDF($html, $filepath)) return;
        if ($this->tryWKHTMLTOPDF($html, $filepath)) return;
        // Fallback: save as HTML
        file_put_contents($filepath . '.html', $html);
    }

    private function tryTCPDF(string $html, string $filepath): bool {
        $tcpdfDir = __DIR__ . '/../../vendor/tecnickcom/tcpdf';
        if (!file_exists($tcpdfDir . '/tcpdf.php')) return false;

        require_once $tcpdfDir . '/tcpdf.php';
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('NOOVA S.A.C.');
        $pdf->SetTitle('Reporte NOOVA');
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filepath, 'F');
        return true;
    }

    private function tryMPDF(string $html, string $filepath): bool {
        $mpdfDir = __DIR__ . '/../../vendor/mpdf/mpdf';
        if (!file_exists($mpdfDir . '/src/Mpdf.php')) return false;

        require_once $mpdfDir . '/src/Mpdf.php';
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
        return true;
    }

    private function tryWKHTMLTOPDF(string $html, string $filepath): bool {
        $wk = 'wkhtmltopdf';
        $tmpFile = tempnam(sys_get_temp_dir(), 'noova_') . '.html';
        file_put_contents($tmpFile, $html);
        exec("\"$wk\" \"$tmpFile\" \"$filepath\" 2>&1", $output, $code);
        unlink($tmpFile);
        return $code === 0 && file_exists($filepath);
    }

    private function htmlToExcel(string $html, string $filepath): void {
        $clean = str_replace(['<table', '</table>'], ['<table border="1"', '</table>'], $html);
        $clean = preg_replace('/<style>.*<\/style>/s', '', $clean);
        $clean = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>' . $clean . '</body></html>';
        file_put_contents($filepath, $clean);
    }

    public function getFormatos(): array {
        return ['pdf', 'excel', 'word'];
    }
}

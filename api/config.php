<?php
/**
 * NOOVA S.A.C. - API Configuration
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'noova_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Site configuration
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/noova3');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('SESSION_LIFETIME', 86400); // 24 hours

// CSRF Protection
define('CSRF_SECRET', getenv('CSRF_SECRET') ?: 'noova_csrf_secret_key_change_in_production');

// Database connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

// JSON response helper
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400): void {
    jsonResponse(['error' => $message], $status);
}

// CSRF Token generation & validation
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Session management
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $savePath = getenv('SESSION_SAVE_PATH') ?: '';
        if ($savePath) {
            session_save_path($savePath);
        }
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

function getCurrentUser(): ?array {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.*, r.nombre as rol_nombre, r.nivel as rol_nivel
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ? AND u.activo = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        jsonError('No autorizado', 401);
    }
    return $user;
}

function requireRole(string ...$roles): array {
    $user = requireAuth();
    if (!in_array($user['rol_nombre'], $roles)) {
        jsonError('Permisos insuficientes', 403);
    }
    return $user;
}

// XSS Prevention
function sanitize(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Input sanitization (single string)
function cleanInput(string $str): string {
    return trim(strip_tags($str));
}

// Input validation
function validateInput(array $data, array $rules): ?string {
    foreach ($rules as $field => $rule) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            if (strpos($rule, 'required') !== false) {
                return "El campo {$field} es requerido";
            }
        }
        if (isset($data[$field])) {
            if (strpos($rule, 'email') !== false && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                return "El campo {$field} debe ser un email válido";
            }
            if (strpos($rule, 'numeric') !== false && !is_numeric($data[$field])) {
                return "El campo {$field} debe ser numérico";
            }
        }
    }
    return null;
}

// File upload helper
function uploadFile(array $file, string $subdir = 'documentos'): ?string {
    $allowedTypes = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'dwg' => 'application/acad',
        'dxf' => 'image/vnd.dxf',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'mp4' => 'video/mp4',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowedTypes[$ext])) {
        return null;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return null;
    }

    $uploadDir = UPLOAD_PATH . $subdir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/' . $subdir . '/' . $filename;
    }

    return null;
}

// Activity logging
function logActivity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $userId = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (usuario_id, accion, entidad_tipo, entidad_id, detalles, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId ?? ($_SESSION['user_id'] ?? null),
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Silent fail for activity logging
    }
}

// Initialize session
startSession();

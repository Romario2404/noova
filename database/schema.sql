-- ============================================
-- NOOVA S.A.C. - Database Schema
-- Version: 1.0.0
-- Engine: MySQL / MariaDB
-- ============================================

CREATE DATABASE IF NOT EXISTS noova_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE noova_db;

-- ============================================
-- USERS & AUTH
-- ============================================

CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    nivel INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (nombre, descripcion, nivel) VALUES
('admin', 'Administrador del sistema - Acceso total', 100),
('supervisor', 'Supervisor de proyectos - Acceso a gestión y reportes', 80),
('asesor', 'Asesor de proyectos - Acceso a clientes y proyectos asignados', 60),
('cliente', 'Cliente - Acceso a sus proyectos y documentos', 40),
('invitado', 'Invitado - Acceso limitado a información pública', 10);

CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rol_id INT NOT NULL,
    codigo VARCHAR(20) UNIQUE,
    dni VARCHAR(8) UNIQUE,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    celular VARCHAR(20),
    foto VARCHAR(255),
    cargo VARCHAR(100),
    empresa VARCHAR(150),
    direccion TEXT,
    ciudad VARCHAR(100),
    pais VARCHAR(100) DEFAULT 'Perú',
    theme VARCHAR(50) DEFAULT 'azul-corporativo',
    modo VARCHAR(10) DEFAULT 'light',
    activo BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    ultimo_acceso TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================
-- CLIENTS & ADVISORS
-- ============================================

CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT UNIQUE,
    codigo VARCHAR(20) UNIQUE,
    razon_social VARCHAR(200) NOT NULL,
    ruc VARCHAR(11) UNIQUE,
    rubro VARCHAR(100),
    sitio_web VARCHAR(255),
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE asesores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT UNIQUE,
    codigo VARCHAR(20) UNIQUE,
    especialidad VARCHAR(200),
    experiencia_anos INT,
    curriculum TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE cliente_asesor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    asesor_id INT NOT NULL,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (asesor_id) REFERENCES asesores(id) ON DELETE CASCADE,
    UNIQUE KEY unique_asignacion (cliente_id, asesor_id)
);

-- ============================================
-- PROJECTS
-- ============================================

CREATE TABLE proyectos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    cliente_id INT,
    asesor_id INT,
    supervisor_id INT,
    estado ENUM('planificacion', 'en_progreso', 'completado', 'suspendido', 'cancelado') DEFAULT 'planificacion',
    prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    tipo ENUM('ventilacion', 'bombeo', 'drenaje', 'topografia', 'fotogrametria', 'simulacion', 'ingenieria', 'gestion', 'equipos', 'otro') DEFAULT 'otro',
    fecha_inicio DATE,
    fecha_fin_estimada DATE,
    fecha_fin_real DATE,
    presupuesto DECIMAL(15,2),
    porcentaje_avance DECIMAL(5,2) DEFAULT 0,
    ubicacion VARCHAR(255),
    notas TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (asesor_id) REFERENCES asesores(id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE proyecto_etapas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    orden INT NOT NULL DEFAULT 0,
    fecha_inicio DATE,
    fecha_fin DATE,
    porcentaje_completado DECIMAL(5,2) DEFAULT 0,
    estado ENUM('pendiente', 'en_progreso', 'completado', 'retrasado') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
);

CREATE TABLE proyecto_indicadores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    valor_actual DECIMAL(15,2),
    valor_meta DECIMAL(15,2),
    unidad VARCHAR(50),
    tipo ENUM('porcentaje', 'numerico', 'monetario') DEFAULT 'numerico',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
);

-- ============================================
-- DOCUMENTS
-- ============================================

CREATE TABLE documentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proyecto_id INT,
    usuario_id INT,
    codigo VARCHAR(30) UNIQUE,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    tipo VARCHAR(50),
    extension VARCHAR(10),
    tamano BIGINT,
    descripcion TEXT,
    version INT DEFAULT 1,
    categoria ENUM('informe', 'plano', 'contrato', 'foto', 'video', 'modelo3d', 'reporte', 'documento', 'otro') DEFAULT 'documento',
    publico BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE documento_versiones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    version INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    tamano BIGINT,
    usuario_id INT,
    cambios TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ============================================
-- COMMUNICATION
-- ============================================

CREATE TABLE mensajes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    remitente_id INT NOT NULL,
    asunto VARCHAR(200),
    cuerpo TEXT NOT NULL,
    leido BOOLEAN DEFAULT FALSE,
    leido_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE mensaje_destinatarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mensaje_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    leido BOOLEAN DEFAULT FALSE,
    leido_at TIMESTAMP NULL,
    FOREIGN KEY (mensaje_id) REFERENCES mensajes(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE mensaje_adjuntos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mensaje_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    tamano BIGINT,
    FOREIGN KEY (mensaje_id) REFERENCES mensajes(id) ON DELETE CASCADE
);

CREATE TABLE chats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proyecto_id INT,
    tipo ENUM('individual', 'grupal', 'proyecto') DEFAULT 'individual',
    nombre VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE SET NULL
);

CREATE TABLE chat_mensajes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chat_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE chat_participantes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chat_id INT NOT NULL,
    usuario_id INT NOT NULL,
    ultima_lectura TIMESTAMP NULL,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participante (chat_id, usuario_id)
);

-- ============================================
-- NOTIFICATIONS
-- ============================================

CREATE TABLE notificaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT,
    referencia_tipo VARCHAR(50),
    referencia_id INT,
    leido BOOLEAN DEFAULT FALSE,
    leido_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================
-- ACTIVITY LOG
-- ============================================

CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    entidad_tipo VARCHAR(50),
    entidad_id INT,
    detalles TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ============================================
-- REPORTS
-- ============================================

CREATE TABLE reportes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    formato ENUM('pdf', 'excel', 'word') DEFAULT 'pdf',
    parametros JSON,
    ruta_archivo VARCHAR(500),
    estado ENUM('generado', 'en_proceso', 'error') DEFAULT 'en_proceso',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- ============================================
-- PROJECT REQUESTS (solicitudes)
-- ============================================

CREATE TABLE solicitudes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    asesor_id INT,
    tipo_proyecto ENUM('ventilacion', 'bombeo', 'drenaje', 'topografia', 'fotogrametria', 'simulacion', 'ingenieria', 'gestion', 'equipos', 'otro') DEFAULT 'otro',
    descripcion TEXT,
    estado ENUM('pendiente', 'aprobado', 'rechazado', 'completado') DEFAULT 'pendiente',
    respuesta_asesor TEXT,
    fecha_respuesta TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (asesor_id) REFERENCES asesores(id) ON DELETE SET NULL
);

-- ============================================
-- SETTINGS & CONFIG
-- ============================================

CREATE TABLE configuraciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO configuraciones (clave, valor, descripcion) VALUES
('site_name', 'NOOVA S.A.C.', 'Nombre del sitio'),
('site_description', 'Consultoría minera, ventilación, bombeo, drenaje, topografía, fotogrametría, simulación, ingeniería y gestión de proyectos.', 'Descripción del sitio'),
('contact_email', 'contacto@noova.pe', 'Email de contacto'),
('contact_phone', '+51 1 234 5678', 'Teléfono de contacto'),
('contact_whatsapp', '+51999888777', 'WhatsApp de contacto'),
('address', 'Av. Principal 1234, San Isidro, Lima, Perú', 'Dirección'),
('default_theme', 'azul-corporativo', 'Tema por defecto'),
('default_mode', 'light', 'Modo por defecto');

-- ============================================
-- INDEXES
-- ============================================

CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_rol ON usuarios(rol_id);
CREATE INDEX idx_proyectos_cliente ON proyectos(cliente_id);
CREATE INDEX idx_proyectos_asesor ON proyectos(asesor_id);
CREATE INDEX idx_proyectos_estado ON proyectos(estado);
CREATE INDEX idx_documentos_proyecto ON documentos(proyecto_id);
CREATE INDEX idx_notificaciones_usuario ON notificaciones(usuario_id);
CREATE INDEX idx_notificaciones_leido ON notificaciones(usuario_id, leido);
CREATE INDEX idx_mensajes_remitente ON mensajes(remitente_id);
CREATE INDEX idx_activity_logs_usuario ON activity_logs(usuario_id);
CREATE INDEX idx_activity_logs_created ON activity_logs(created_at);

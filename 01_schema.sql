CREATE DATABASE IF NOT EXISTS planta_agregados;
USE planta_agregados;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(30) NOT NULL DEFAULT 'Operador',
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(30),
    telefono VARCHAR(30),
    direccion VARCHAR(255),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    unidad VARCHAR(20) DEFAULT 'm3',
    precio DECIMAL(12,2) DEFAULT 0,
    existencia DECIMAL(12,2) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    cliente_id INT NULL,
    material_id INT NULL,
    cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
    precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado VARCHAR(30) DEFAULT 'Pendiente',
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS produccion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    material_id INT NULL,
    cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
    turno VARCHAR(50),
    operador VARCHAR(100),
    observaciones TEXT,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS despachos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    cliente_id INT NULL,
    material_id INT NULL,
    cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
    vehiculo VARCHAR(100),
    piloto VARCHAR(100),
    estado VARCHAR(30) DEFAULT 'Pendiente',
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS combustible_compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    proveedor VARCHAR(150),
    galones DECIMAL(12,2) DEFAULT 0,
    precio_galon DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    factura VARCHAR(100),
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS maquinaria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(50) UNIQUE,
    marca VARCHAR(80),
    modelo VARCHAR(80),
    horometro_actual DECIMAL(12,2) DEFAULT 0,
    estado VARCHAR(30) DEFAULT 'Operativa',
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS combustible_despachos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    maquinaria_id INT NULL,
    operador VARCHAR(100),
    galones DECIMAL(12,2) DEFAULT 0,
    precio_galon DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    horometro_despacho DECIMAL(12,2) DEFAULT 0,
    observaciones TEXT,
    FOREIGN KEY (maquinaria_id) REFERENCES maquinaria(id) ON DELETE SET NULL
);

INSERT INTO usuarios (nombre, usuario, password, rol)
VALUES ('Administrador', 'admin', '$2y$10$mC28kFpD1L1.kR7Lh18gNuWvF2a8W3MvKbeZt5f3Y9g2f2U.gL50S', 'Administrador')
ON DUPLICATE KEY UPDATE usuario = usuario;

INSERT INTO materiales (nombre, unidad, precio, existencia)
VALUES
('Piedrín 1/2"', 'm3', 160.00, 128),
('Arena', 'm3', 120.00, 74),
('Polvo de piedra', 'm3', 100.00, 51)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO maquinaria (nombre, codigo, marca, modelo, estado, horometro_actual)
VALUES
('Cargador frontal', 'MAQ-001', 'XCMG', 'LW500FN', 'Operativa', 1240.50),
('Trituradora', 'MAQ-002', 'Planta', 'Trituración', 'Operativa', 3150.20),
('Criba vibratoria', 'MAQ-003', 'Planta', '4x8', 'Operativa', 850.00)
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

INSERT INTO combustible_compras (fecha, proveedor, galones, precio_galon, total, factura)
VALUES (CURDATE(), 'Chevron Distribuidora', 4500.00, 28.50, 128250.00, 'FAC-0091');

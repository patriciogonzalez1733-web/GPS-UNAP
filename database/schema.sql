CREATE DATABASE IF NOT EXISTS proyecto_titulo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proyecto_titulo;

CREATE TABLE pisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    imagen VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) NOT NULL DEFAULT 'lugar.png'
);

CREATE TABLE nodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    piso_id INT NOT NULL,
    etiqueta VARCHAR(100) DEFAULT NULL,
    tipo ENUM('pasillo', 'entrada', 'escalera') NOT NULL DEFAULT 'pasillo',
    coord_x DECIMAL(6,3) NOT NULL,
    coord_y DECIMAL(6,3) NOT NULL,
    FOREIGN KEY (piso_id) REFERENCES pisos(id) ON DELETE CASCADE
);

CREATE TABLE conexiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nodo_origen_id INT NOT NULL,
    nodo_destino_id INT NOT NULL,
    distancia DECIMAL(8,3) NOT NULL,
    FOREIGN KEY (nodo_origen_id) REFERENCES nodos(id) ON DELETE CASCADE,
    FOREIGN KEY (nodo_destino_id) REFERENCES nodos(id) ON DELETE CASCADE
);

CREATE TABLE ubicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    piso_id INT NOT NULL,
    categoria_id INT NOT NULL,
    nodo_cercano_id INT DEFAULT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    imagen_referencia VARCHAR(255) DEFAULT NULL,
    coord_x DECIMAL(6,3) NOT NULL,
    coord_y DECIMAL(6,3) NOT NULL,
    FOREIGN KEY (piso_id) REFERENCES pisos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (nodo_cercano_id) REFERENCES nodos(id) ON DELETE SET NULL
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL
);

INSERT INTO categorias (nombre, icono) VALUES
    ('Aula', 'aula.png'),
    ('Laboratorio', 'laboratorio.png'),
    ('Oficina', 'oficina.png'),
    ('Baño', 'bano.png'),
    ('Servicio', 'servicio.png');

INSERT INTO pisos (nombre, imagen) VALUES ('Piso 1', 'piso1.png'), ('Piso 2', 'piso2.png');
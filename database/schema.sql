-- Schema del GPS universitario (nombres claros)
-- ATENCION: borra y recrea proyecto_titulo. Se pierden los datos actuales.

DROP DATABASE IF EXISTS proyecto_titulo;
CREATE DATABASE proyecto_titulo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE proyecto_titulo;

CREATE TABLE pisos (
    id_piso INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    imagen VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50) NOT NULL DEFAULT 'lugar.png'
);

CREATE TABLE nodos (
    id_nodo INT AUTO_INCREMENT PRIMARY KEY,
    id_piso INT NOT NULL,
    etiqueta VARCHAR(100) DEFAULT NULL,
    tipo ENUM('pasillo', 'entrada', 'escalera') NOT NULL DEFAULT 'pasillo',
    imagen_referencia VARCHAR(255) DEFAULT NULL,
    coord_x DECIMAL(6,3) NOT NULL,
    coord_y DECIMAL(6,3) NOT NULL,
    FOREIGN KEY (id_piso) REFERENCES pisos(id_piso) ON DELETE CASCADE
);

CREATE TABLE conexiones (
    id_conexion INT AUTO_INCREMENT PRIMARY KEY,
    id_nodo_origen INT NOT NULL,
    id_nodo_destino INT NOT NULL,
    distancia DECIMAL(8,3) NOT NULL,
    FOREIGN KEY (id_nodo_origen) REFERENCES nodos(id_nodo) ON DELETE CASCADE,
    FOREIGN KEY (id_nodo_destino) REFERENCES nodos(id_nodo) ON DELETE CASCADE
);

CREATE TABLE ubicaciones (
    id_ubicacion INT AUTO_INCREMENT PRIMARY KEY,
    id_piso INT NOT NULL,
    id_categoria INT NOT NULL,
    id_nodo_cercano INT DEFAULT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    imagen_referencia VARCHAR(255) DEFAULT NULL,
    coord_x DECIMAL(6,3) NOT NULL,
    coord_y DECIMAL(6,3) NOT NULL,
    FOREIGN KEY (id_piso) REFERENCES pisos(id_piso) ON DELETE CASCADE,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
    FOREIGN KEY (id_nodo_cercano) REFERENCES nodos(id_nodo) ON DELETE SET NULL
);

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL
);

INSERT INTO categorias (nombre, icono) VALUES
    ('Aula', 'aula.png'),
    ('Laboratorio', 'laboratorio.png'),
    ('Oficina', 'oficina.png'),
    ('Baño', 'bano.png'),
    ('Servicio', 'servicio.png');

INSERT INTO pisos (nombre, imagen) VALUES ('Piso 1', 'piso1_recortado.png'), ('Piso 2', 'piso2_recortado.png');

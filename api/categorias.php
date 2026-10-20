<?php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT * FROM categorias ORDER BY nombre');
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($datos['nombre'] ?? '');
    $icono  = trim($datos['icono'] ?? 'lugar.png');

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['error' => 'El nombre es obligatorio']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO categorias (nombre, icono) VALUES (?, ?)');
    $stmt->execute([$nombre, $icono]);
    echo json_encode(['id' => $pdo->lastInsertId(), 'nombre' => $nombre, 'icono' => $icono]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
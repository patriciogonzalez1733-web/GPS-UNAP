<?php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $sql = 'SELECT n.*, p.nombre AS piso_nombre FROM nodos n JOIN pisos p ON p.id = n.piso_id';

    if (isset($_GET['piso_id'])) {
        $stmt = $pdo->prepare($sql . ' WHERE n.piso_id = ?');
        $stmt->execute([(int) $_GET['piso_id']]);
    } else {
        $stmt = $pdo->query($sql);
    }

    echo json_encode($stmt->fetchAll());
    exit;
}

if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare(
        'INSERT INTO nodos (piso_id, etiqueta, tipo, coord_x, coord_y) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $datos['piso_id'],
        $datos['etiqueta'] ?? null,
        $datos['tipo'] ?? 'pasillo',
        (float) $datos['coord_x'],
        (float) $datos['coord_y'],
    ]);
    echo json_encode(['id' => $pdo->lastInsertId()]);
    exit;
}

if ($metodo === 'PUT') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare('UPDATE nodos SET coord_x = ?, coord_y = ? WHERE id = ?');
    $stmt->execute([(float) $datos['coord_x'], (float) $datos['coord_y'], (int) $datos['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM nodos WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
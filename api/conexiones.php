<?php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $pisoId = (int) ($_GET['piso_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT c.* FROM conexiones c
         JOIN nodos n ON n.id = c.nodo_origen_id
         WHERE n.piso_id = ?'
    );
    $stmt->execute([$pisoId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $origenId = (int) $datos['nodo_origen_id'];
    $destinoId = (int) $datos['nodo_destino_id'];

    if ($origenId === $destinoId) {
        http_response_code(400);
        echo json_encode(['error' => 'Un nodo no puede conectarse consigo mismo']);
        exit;
    }

    if (isset($datos['distancia'])) {
        $distancia = (float) $datos['distancia'];
    } else {
        $stmt = $pdo->prepare('SELECT id, coord_x, coord_y FROM nodos WHERE id IN (?, ?)');
        $stmt->execute([$origenId, $destinoId]);
        $nodos = $stmt->fetchAll();

        if (count($nodos) !== 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Nodo origen o destino no existe']);
            exit;
        }

        $distancia = sqrt(
            pow($nodos[0]['coord_x'] - $nodos[1]['coord_x'], 2) +
            pow($nodos[0]['coord_y'] - $nodos[1]['coord_y'], 2)
        );
    }

    $stmt = $pdo->prepare('INSERT INTO conexiones (nodo_origen_id, nodo_destino_id, distancia) VALUES (?, ?, ?)');
    $stmt->execute([$origenId, $destinoId, $distancia]);
    $idIda = $pdo->lastInsertId();
    $stmt->execute([$destinoId, $origenId, $distancia]);
    $idVuelta = $pdo->lastInsertId();

    echo json_encode(['id' => $idIda, 'id_vuelta' => $idVuelta, 'distancia' => $distancia]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT nodo_origen_id, nodo_destino_id FROM conexiones WHERE id = ?');
    $stmt->execute([$id]);
    $conexion = $stmt->fetch();

    if ($conexion) {
        $stmt = $pdo->prepare(
            'DELETE FROM conexiones WHERE
             (nodo_origen_id = ? AND nodo_destino_id = ?) OR
             (nodo_origen_id = ? AND nodo_destino_id = ?)'
        );
        $stmt->execute([
            $conexion['nodo_origen_id'], $conexion['nodo_destino_id'],
            $conexion['nodo_destino_id'], $conexion['nodo_origen_id'],
        ]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
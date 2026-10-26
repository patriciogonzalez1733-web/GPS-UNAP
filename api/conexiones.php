<?php

require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'GET') {
    exigirAdminApi();
}

if ($metodo === 'GET') {
    $pisoId = (int) ($_GET['id_piso'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT c.* FROM conexiones c
         JOIN nodos n ON n.id_nodo = c.id_nodo_origen
         WHERE n.id_piso = ?'
    );
    $stmt->execute([$pisoId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $origenId = (int) $datos['id_nodo_origen'];
    $destinoId = (int) $datos['id_nodo_destino'];

    if ($origenId === $destinoId) {
        http_response_code(400);
        echo json_encode(['error' => 'Un nodo no puede conectarse consigo mismo']);
        exit;
    }

    if (isset($datos['distancia'])) {
        $distancia = (float) $datos['distancia'];
    } else {
        $stmt = $pdo->prepare('SELECT id_nodo, coord_x, coord_y FROM nodos WHERE id_nodo IN (?, ?)');
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

    $stmt = $pdo->prepare('INSERT INTO conexiones (id_nodo_origen, id_nodo_destino, distancia) VALUES (?, ?, ?)');
    $stmt->execute([$origenId, $destinoId, $distancia]);
    $idIda = $pdo->lastInsertId();
    $stmt->execute([$destinoId, $origenId, $distancia]);
    $idVuelta = $pdo->lastInsertId();

    echo json_encode(['id_conexion' => $idIda, 'id_vuelta' => $idVuelta, 'distancia' => $distancia]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id_conexion'] ?? 0);

    $stmt = $pdo->prepare('SELECT id_nodo_origen, id_nodo_destino FROM conexiones WHERE id_conexion = ?');
    $stmt->execute([$id]);
    $conexion = $stmt->fetch();

    if ($conexion) {
        $stmt = $pdo->prepare(
            'DELETE FROM conexiones WHERE
             (id_nodo_origen = ? AND id_nodo_destino = ?) OR
             (id_nodo_origen = ? AND id_nodo_destino = ?)'
        );
        $stmt->execute([
            $conexion['id_nodo_origen'], $conexion['id_nodo_destino'],
            $conexion['id_nodo_destino'], $conexion['id_nodo_origen'],
        ]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
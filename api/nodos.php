<?php

require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'GET') {
    exigirAdminApi();
}

if ($metodo === 'GET') {
    $sql = 'SELECT n.*, p.nombre AS piso_nombre FROM nodos n JOIN pisos p ON p.id_piso = n.id_piso';

    if (isset($_GET['id_piso'])) {
        $stmt = $pdo->prepare($sql . ' WHERE n.id_piso = ?');
        $stmt->execute([(int) $_GET['id_piso']]);
    } else {
        $stmt = $pdo->query($sql);
    }

    echo json_encode($stmt->fetchAll());
    exit;
}

if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare(
        'INSERT INTO nodos (id_piso, etiqueta, tipo, coord_x, coord_y) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $datos['id_piso'],
        $datos['etiqueta'] ?? null,
        $datos['tipo'] ?? 'pasillo',
        (float) $datos['coord_x'],
        (float) $datos['coord_y'],
    ]);
    echo json_encode(['id_nodo' => $pdo->lastInsertId()]);
    exit;
}

if ($metodo === 'PUT') {
    $datos = json_decode(file_get_contents('php://input'), true);
    $id = (int) $datos['id_nodo'];

    $stmt = $pdo->prepare('SELECT * FROM nodos WHERE id_nodo = ?');
    $stmt->execute([$id]);
    $actual = $stmt->fetch();

    if (!$actual) {
        http_response_code(404);
        echo json_encode(['error' => 'El nodo no existe']);
        exit;
    }

    $tipo = $datos['tipo'] ?? $actual['tipo'];
    if (!in_array($tipo, ['pasillo', 'entrada', 'escalera'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de nodo no válido']);
        exit;
    }

    $etiqueta = array_key_exists('etiqueta', $datos) ? $datos['etiqueta'] : $actual['etiqueta'];
    $coordX = isset($datos['coord_x']) ? (float) $datos['coord_x'] : $actual['coord_x'];
    $coordY = isset($datos['coord_y']) ? (float) $datos['coord_y'] : $actual['coord_y'];

    $stmt = $pdo->prepare('UPDATE nodos SET tipo = ?, etiqueta = ?, coord_x = ?, coord_y = ? WHERE id_nodo = ?');
    $stmt->execute([$tipo, $etiqueta, $coordX, $coordY, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id_nodo'] ?? 0);

    $stmt = $pdo->prepare('SELECT imagen_referencia FROM nodos WHERE id_nodo = ?');
    $stmt->execute([$id]);
    $ruta = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM nodos WHERE id_nodo = ?');
    $stmt->execute([$id]);

    if ($ruta && str_starts_with($ruta, 'uploads/nodos/') && !str_contains($ruta, '..')) {
        $archivo = __DIR__ . '/../' . $ruta;
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
<?php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $sql = 'SELECT u.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono, p.nombre AS piso_nombre
            FROM ubicaciones u
            JOIN categorias c ON c.id = u.categoria_id
            JOIN pisos p ON p.id = u.piso_id';

    if (isset($_GET['piso_id'])) {
        $stmt = $pdo->prepare($sql . ' WHERE u.piso_id = ?');
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
        'INSERT INTO ubicaciones (piso_id, categoria_id, nodo_cercano_id, nombre, descripcion, coord_x, coord_y)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $datos['piso_id'],
        (int) $datos['categoria_id'],
        !empty($datos['nodo_cercano_id']) ? (int) $datos['nodo_cercano_id'] : null,
        trim($datos['nombre']),
        $datos['descripcion'] ?? null,
        (float) $datos['coord_x'],
        (float) $datos['coord_y'],
    ]);
    echo json_encode(['id' => $pdo->lastInsertId()]);
    exit;
}

if ($metodo === 'PUT') {
    $datos = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare(
        'UPDATE ubicaciones SET categoria_id = ?, nodo_cercano_id = ?, nombre = ?, descripcion = ?, coord_x = ?, coord_y = ?
         WHERE id = ?'
    );
    $stmt->execute([
        (int) $datos['categoria_id'],
        !empty($datos['nodo_cercano_id']) ? (int) $datos['nodo_cercano_id'] : null,
        trim($datos['nombre']),
        $datos['descripcion'] ?? null,
        (float) $datos['coord_x'],
        (float) $datos['coord_y'],
        (int) $datos['id'],
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT imagen_referencia FROM ubicaciones WHERE id = ?');
    $stmt->execute([$id]);
    $ruta = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM ubicaciones WHERE id = ?');
    $stmt->execute([$id]);

    if ($ruta && str_starts_with($ruta, 'uploads/ubicaciones/') && !str_contains($ruta, '..')) {
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
<?php

require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'GET') {
    exigirAdminApi();
}

if ($metodo === 'GET') {
    $sql = 'SELECT u.*, c.nombre AS categoria_nombre, c.icono AS categoria_icono, p.nombre AS piso_nombre
            FROM ubicaciones u
            JOIN categorias c ON c.id_categoria = u.id_categoria
            JOIN pisos p ON p.id_piso = u.id_piso';

    if (isset($_GET['id_piso'])) {
        $stmt = $pdo->prepare($sql . ' WHERE u.id_piso = ?');
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
        'INSERT INTO ubicaciones (id_piso, id_categoria, id_nodo_cercano, nombre, descripcion, coord_x, coord_y)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $datos['id_piso'],
        (int) $datos['id_categoria'],
        !empty($datos['id_nodo_cercano']) ? (int) $datos['id_nodo_cercano'] : null,
        trim($datos['nombre']),
        $datos['descripcion'] ?? null,
        (float) $datos['coord_x'],
        (float) $datos['coord_y'],
    ]);
    echo json_encode(['id_ubicacion' => $pdo->lastInsertId()]);
    exit;
}

if ($metodo === 'PUT') {
    $datos = json_decode(file_get_contents('php://input'), true);

    $stmt = $pdo->prepare(
        'UPDATE ubicaciones SET id_categoria = ?, id_nodo_cercano = ?, nombre = ?, descripcion = ?, coord_x = ?, coord_y = ?
         WHERE id_ubicacion = ?'
    );
    $stmt->execute([
        (int) $datos['id_categoria'],
        !empty($datos['id_nodo_cercano']) ? (int) $datos['id_nodo_cercano'] : null,
        trim($datos['nombre']),
        $datos['descripcion'] ?? null,
        (float) $datos['coord_x'],
        (float) $datos['coord_y'],
        (int) $datos['id_ubicacion'],
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id_ubicacion'] ?? 0);

    $stmt = $pdo->prepare('SELECT imagen_referencia FROM ubicaciones WHERE id_ubicacion = ?');
    $stmt->execute([$id]);
    $ruta = $stmt->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM ubicaciones WHERE id_ubicacion = ?');
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
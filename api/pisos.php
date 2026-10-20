<?php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$carpetaUploads = __DIR__ . '/../uploads/pisos/';

if ($metodo === 'GET') {
    $stmt = $pdo->query('SELECT * FROM pisos ORDER BY id');
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($metodo === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '' || empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta el nombre o la imagen del plano']);
        exit;
    }

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de imagen no permitido (usa jpg, png o webp)']);
        exit;
    }

    if (!is_dir($carpetaUploads)) {
        mkdir($carpetaUploads, 0777, true);
    }

    $nombreArchivo = uniqid('piso_') . '.' . $extension;
    move_uploaded_file($_FILES['imagen']['tmp_name'], $carpetaUploads . $nombreArchivo);

    $stmt = $pdo->prepare('INSERT INTO pisos (nombre, imagen) VALUES (?, ?)');
    $stmt->execute([$nombre, $nombreArchivo]);
    echo json_encode(['id' => $pdo->lastInsertId(), 'nombre' => $nombre, 'imagen' => $nombreArchivo]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT imagen FROM pisos WHERE id = ?');
    $stmt->execute([$id]);
    $piso = $stmt->fetch();

    $stmt = $pdo->prepare('DELETE FROM pisos WHERE id = ?');
    $stmt->execute([$id]);

    if ($piso && file_exists($carpetaUploads . $piso['imagen'])) {
        unlink($carpetaUploads . $piso['imagen']);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
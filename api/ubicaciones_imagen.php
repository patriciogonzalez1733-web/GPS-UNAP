<?php

require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'GET') {
    exigirAdminApi();
}
$carpetaRelativa = 'uploads/ubicaciones/';
$carpetaUploads = __DIR__ . '/../' . $carpetaRelativa;

function borrarImagenAnterior(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT imagen_referencia FROM ubicaciones WHERE id_ubicacion = ?');
    $stmt->execute([$id]);
    $ruta = $stmt->fetchColumn();

    if ($ruta && str_starts_with($ruta, 'uploads/ubicaciones/') && !str_contains($ruta, '..')) {
        $archivo = __DIR__ . '/../' . $ruta;
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
}

if ($metodo === 'POST') {
    $id = (int) ($_POST['id_ubicacion'] ?? 0);

    if ($id === 0 || empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta la ubicación o la imagen']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id_ubicacion FROM ubicaciones WHERE id_ubicacion = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'La ubicación no existe']);
        exit;
    }

    if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'La imagen supera los 5 MB']);
        exit;
    }

    $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
    $esImagen = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
        && getimagesize($_FILES['imagen']['tmp_name']) !== false;

    if (!$esImagen) {
        http_response_code(400);
        echo json_encode(['error' => 'El archivo no es una imagen válida (usa jpg, png o webp)']);
        exit;
    }

    if (!is_dir($carpetaUploads)) {
        mkdir($carpetaUploads, 0777, true);
    }

    borrarImagenAnterior($pdo, $id);

    $nombreArchivo = uniqid('ubic_') . '.' . $extension;
    move_uploaded_file($_FILES['imagen']['tmp_name'], $carpetaUploads . $nombreArchivo);

    $ruta = $carpetaRelativa . $nombreArchivo;
    $stmt = $pdo->prepare('UPDATE ubicaciones SET imagen_referencia = ? WHERE id_ubicacion = ?');
    $stmt->execute([$ruta, $id]);

    echo json_encode(['ok' => true, 'imagen_referencia' => $ruta]);
    exit;
}

if ($metodo === 'DELETE') {
    $id = (int) ($_GET['id_ubicacion'] ?? 0);

    borrarImagenAnterior($pdo, $id);

    $stmt = $pdo->prepare('UPDATE ubicaciones SET imagen_referencia = NULL WHERE id_ubicacion = ?');
    $stmt->execute([$id]);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);

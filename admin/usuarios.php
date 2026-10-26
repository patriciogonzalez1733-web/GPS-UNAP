<?php
require_once __DIR__ . '/../config/auth.php';

$hayAdmins = hayAdministradores($pdo);
if ($hayAdmins && adminActual() === null) {
    header('Location: login.php');
    exit;
}

$error = '';
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = mb_strtolower(trim($_POST['correo'] ?? ''));
        $contrasena = $_POST['contrasena'] ?? '';
        $repetir = $_POST['repetir'] ?? '';

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $error = 'El nombre es obligatorio (máximo 100 caracteres)';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 150) {
            $error = 'Ingresa un correo válido';
        } elseif (strlen($contrasena) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif ($contrasena !== $repetir) {
            $error = 'Las contraseñas no coinciden';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = ?');
            $stmt->execute([$correo]);

            if ($stmt->fetch()) {
                $error = 'Ya existe un administrador con ese correo';
            } else {
                $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, correo, contrasena) VALUES (?, ?, ?)');
                $stmt->execute([$nombre, $correo, password_hash($contrasena, PASSWORD_DEFAULT)]);

                if (!$hayAdmins) {
                    iniciarSesionAdmin(['id_usuario' => $pdo->lastInsertId(), 'nombre' => $nombre]);
                    header('Location: editor.php');
                    exit;
                }
                $mensaje = 'Administrador creado';
            }
        }
    }

    if ($accion === 'eliminar' && $hayAdmins) {
        $id = (int) ($_POST['id_usuario'] ?? 0);

        if ($id === adminActual()['id_usuario']) {
            $error = 'No puedes eliminar tu propia cuenta';
        } else {
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
            $stmt->execute([$id]);
            $mensaje = 'Administrador eliminado';
        }
    }
}

$administradores = $hayAdmins
    ? $pdo->query('SELECT id_usuario, nombre, correo FROM usuarios ORDER BY nombre')->fetchAll()
    : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $hayAdmins ? 'Administradores' : 'Crear primer administrador' ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=5">
    <style>
        .tarjeta { max-width: 420px; margin: 24px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .tarjeta h2 { margin: 0 0 12px; font-size: 16px; }
        .tarjeta .campo { margin-bottom: 12px; }
        .tarjeta input { width: 100%; }
        .error { background: #fee2e2; color: #991b1b; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; font-size: 14px; }
        .ok { background: #dcfce7; color: #166534; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; font-size: 14px; }
        .fila-admin { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
        .fila-admin form { margin: 0; }
    </style>
</head>
<body>
    <header class="barra">
        <img src="../assets/img/logo-unap.png" alt="UNAP" class="logo">
        <h1><?= $hayAdmins ? 'Administradores' : 'Crear primer administrador' ?></h1>
        <?php if ($hayAdmins): ?>
            <a href="editor.php">Volver al editor</a>
            <a href="logout.php">Salir</a>
        <?php endif; ?>
    </header>

    <main>
        <section class="tarjeta">
            <?php if (!$hayAdmins): ?>
                <p style="font-size:14px;">Aún no hay ningún administrador. Crea el primero para poder usar el panel.</p>
            <?php endif; ?>

            <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($mensaje): ?><div class="ok"><?= e($mensaje) ?></div><?php endif; ?>

            <h2><?= $hayAdmins ? 'Agregar administrador' : 'Primer administrador' ?></h2>
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                <div class="campo">
                    <label for="nombre">Nombre (se muestra en el panel)</label>
                    <input type="text" id="nombre" name="nombre" required maxlength="100">
                </div>
                <div class="campo">
                    <label for="correo">Correo (con el que inicia sesión)</label>
                    <input type="email" id="correo" name="correo" required maxlength="150" autocomplete="username">
                </div>
                <div class="campo">
                    <label for="contrasena">Contraseña (mínimo 8 caracteres)</label>
                    <input type="password" id="contrasena" name="contrasena" required minlength="8" autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="repetir">Repetir contraseña</label>
                    <input type="password" id="repetir" name="repetir" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit">Crear</button>
            </form>
        </section>

        <?php if ($hayAdmins): ?>
            <section class="tarjeta">
                <h2>Administradores actuales</h2>
                <?php foreach ($administradores as $admin): ?>
                    <div class="fila-admin">
                        <span><?= e($admin['nombre']) ?> <span class="ayuda">(<?= e($admin['correo']) ?>)</span></span>
                        <?php if ((int) $admin['id_usuario'] !== adminActual()['id_usuario']): ?>
                            <form method="post" onsubmit="return confirm('¿Eliminar a este administrador?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_usuario" value="<?= (int) $admin['id_usuario'] ?>">
                                <button type="submit" class="peligro">Eliminar</button>
                            </form>
                        <?php else: ?>
                            <span class="ayuda">(tú)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>

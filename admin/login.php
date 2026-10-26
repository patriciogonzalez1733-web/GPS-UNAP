<?php
require_once __DIR__ . '/../config/auth.php';

if (!hayAdministradores($pdo)) {
    header('Location: usuarios.php');
    exit;
}
if (adminActual() !== null) {
    header('Location: editor.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = mb_strtolower(trim($_POST['correo'] ?? ''));
    $contrasena = $_POST['contrasena'] ?? '';

    $stmt = $pdo->prepare('SELECT id_usuario, nombre, contrasena FROM usuarios WHERE correo = ?');
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
        iniciarSesionAdmin($usuario);
        header('Location: editor.php');
        exit;
    }

    usleep(500000);
    $error = 'Correo o contraseña incorrectos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=5">
    <style>
        .tarjeta { max-width: 360px; margin: 32px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .tarjeta .campo { margin-bottom: 12px; }
        .tarjeta input { width: 100%; }
        .error { background: #fee2e2; color: #991b1b; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; font-size: 14px; }
    </style>
</head>
<body>
    <header class="barra">
        <img src="../assets/img/logo-unap.png" alt="UNAP" class="logo">
        <h1>Acceso de administrador</h1>
        <a href="../index.php">Volver al mapa</a>
    </header>

    <main class="tarjeta">
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <div class="campo">
                <label for="correo">Correo</label>
                <input type="email" id="correo" name="correo" required autofocus autocomplete="username">
            </div>
            <div class="campo">
                <label for="contrasena">Contraseña</label>
                <input type="password" id="contrasena" name="contrasena" required autocomplete="current-password">
            </div>
            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>

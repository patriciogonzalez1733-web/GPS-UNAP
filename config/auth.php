<?php

require_once __DIR__ . '/db.php';

const DURACION_SESION = 24 * 60;

function abrirSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', (string) DURACION_SESION);
        session_name('gps_unap');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
        ]);
        session_start();

        if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > DURACION_SESION) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['ultima_actividad'] = time();
    }
}

function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function hayAdministradores(PDO $pdo): bool
{
    return (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
}

function adminActual(): ?array
{
    abrirSesion();
    if (!isset($_SESSION['id_usuario'])) {
        return null;
    }
    return ['id_usuario' => (int) $_SESSION['id_usuario'], 'nombre' => $_SESSION['nombre']];
}

function iniciarSesionAdmin(array $usuario): void
{
    abrirSesion();
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
    $_SESSION['nombre'] = $usuario['nombre'];
}

function exigirAdminPagina(PDO $pdo): void
{
    if (!hayAdministradores($pdo)) {
        header('Location: usuarios.php');
        exit;
    }
    if (adminActual() === null) {
        header('Location: login.php');
        exit;
    }
}

function exigirAdminApi(): void
{
    abrirSesion();
    $autorizado = isset($_SESSION['id_usuario']);
    session_write_close();

    if (!$autorizado) {
        http_response_code(401);
        echo json_encode(['error' => 'Necesitas iniciar sesión como administrador']);
        exit;
    }
}

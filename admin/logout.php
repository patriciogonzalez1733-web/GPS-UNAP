<?php
require_once __DIR__ . '/../config/auth.php';

abrirSesion();
$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;

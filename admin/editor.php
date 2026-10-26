<?php
require_once __DIR__ . '/../config/auth.php';
exigirAdminPagina($pdo);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editor de mapa - Admin </title>
        <link rel= "stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="../assets/css/style.css?v=5">
         <style>
        .editor-layout { display: flex; }
        #mapa { height: calc(100vh - 160px); flex: 1; }
        #barraLateral {
            width: 320px;
            height: calc(100vh - 160px);
            overflow-y: auto;
            background: white;
            border-left: 1px solid #e2e5ea;
            padding: 14px;
        }
        .modos { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .modos button { text-align: left; }
        #barraLateral h3 { margin: 16px 0 8px; font-size: 14px; color: #374151; }
        .item-lista {
            display: flex; justify-content: space-between; align-items: center;
            padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px;
        }
        .item-lista button { padding: 3px 8px; font-size: 12px; }
        #formPendiente { background: #f8fafc; padding: 10px; border-radius: 6px; margin-bottom: 12px; display: none; }
        #formPendiente .campo { margin-bottom: 8px; }
        #formPendiente select, #formPendiente input, #formPendiente textarea { width: 100%; }
    </style>
    </head>
    <body>
        <header class="barra">
            <img src="../assets/img/logo-unap.png" alt="UNAP" class="logo">
            <h1>Editor de mapa </h1>
            <a href="../index.php"> Volver al mapa </a>
            <span>👤 <?= e(adminActual()['nombre']) ?></span>
            <a href="usuarios.php">Administradores</a>
            <a href="logout.php">Salir</a>
</header>
  <div class="panel-controles">
        <div class="campo">
            <label for="selectorPiso">Piso a editar</label>
            <select id="selectorPiso"></select>
        </div>
        <div class="leyenda">
            🔘 gris = pasillo · 🟢 verde = entrada · 🟠 naranja = escalera · líneas = conexión
        </div>
    </div>

    <div class="editor-layout">
        <div id="mapa"></div>
        <div id="barraLateral">
            <div class="modos">
                <button id="modoVer" class="modo-activo">👁️ Ver / eliminar</button>
                <button id="modoNodo">➕ Agregar pasillo (nodo)</button>
                <button id="modoConectar">🔗 Conectar dos pasillos</button>
                <button id="modoUbicacion">📍 Agregar ubicación</button>
            </div>

            <div id="formPendiente"></div>

            <h3>Pasillos (nodos) — <span id="contadorNodos">0</span></h3>
            <div class="campo" style="display:flex; gap:6px; margin-bottom:8px;">
                <input type="text" id="buscadorNodos" placeholder="Buscar por # o etiqueta..." autocomplete="off" style="flex:1;">
                <select id="filtroTipoNodo">
                    <option value="">Todos</option>
                    <option value="pasillo">Pasillo</option>
                    <option value="entrada">Entrada</option>
                    <option value="escalera">Escalera</option>
                </select>
            </div>
            <div id="listaNodos"></div>

            <h3>Ubicaciones — <span id="contadorUbicaciones">0</span></h3>
            <div id="listaUbicaciones"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../assets/js/mapa.js?v=4"></script>
    <script src="../assets/js/editor.js?v=2"></script>
</body>
</html>
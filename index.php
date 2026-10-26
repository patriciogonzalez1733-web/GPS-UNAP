<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia Campus Unap</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body class="visor">
    <header class="barra">
      <img src="assets/img/logo-unap.png" alt="UNAP" class="logo">
        <h1> 🗺️ Guía Campus Unap</h1>
        <a href="admin/editor.php">Panel de administración →</a>
    </header>

    <div class="panel-controles">
        <div class="campo">
            <label for="selectorPiso">Piso mostrado</label>
            <select id="selectorPiso"></select>
        </div>
        <div class="campo buscador">
            <label for="buscador">Buscar</label>
            <input type="text" id="buscador" placeholder="Sala o entrada..." autocomplete="off">
            <div id="resultadosBusqueda" class="resultados-busqueda" style="display:none;"></div>
        </div>
        <div class="campo">
            <label for="filtroUbicaciones">Mostrar ubicaciones</label>
            <select id="filtroUbicaciones"></select>
        </div>
        <button id="btnLimpiarRuta" class="secundario">Limpiar</button>
        <div id="seleccion" class="seleccion"></div>
    </div>

    <div id="mapa"></div>
    <div id="infoRuta" class="info-ruta" style="display:none;"></div>

    <div id="popupUbicacion" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
         background:white; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.3); padding:16px; width:320px; max-width:90vw; z-index:2000;">
        <button onclick="cerrarPopup()" style="float:right; background:none; color:#6b7280; padding:2px 8px; font-size:16px;">✕</button>
        <h3 id="popupNombre" style="margin:0 0 6px;"></h3>
        <p id="popupDescripcion" style="margin:0 0 10px; font-size:14px; color:#4b5563;"></p>
        <img id="popupImagen" src="" style="width:100%; border-radius:6px; display:none;">
        <div id="popupAcciones" class="popup-acciones"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/mapa.js?v=4"></script>
    <script src="assets/js/visor.js"></script>
</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia Campus Unap</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="barra">
      <img src="assets/img/logo-unap.png" alt="UNAP" class="logo">
        <h1> 🗺️ Guía Campus Unap</h1>
        <a href="admin/editor.php">Panel de administración →</a>
    </header>

    <div class="panel-controles">
        <div class="campo">
            <label for="selectorPiso">Piso mostrado </label>
            <select id="selectorPiso"></select>
        </div>
        <div class="campo">
            <label for="selectorOrigen">desde</label>
            <select id="selectorOrigen"><option value="">seleciona un punto de partida</option></select>
            </div>
        <div class="campo">
            <label for="selectorDestino">Hacia</label>
            <select id="selectorDestino"><option value="">Seleciona un destino</option></select>
        </div>
        <button id="btnCalcularRuta">Calcular ruta</button>
        <button id="btnLimpiarRuta" class="secundario">Limpiar</button>
    </div>
    
    <div id="mapa"></div>
    <div id="infoRuta" class="info-ruta" style="display:none;"></div>

    <div id="popupUbicacion" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
         background:white; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.3); padding:16px; width:320px; z-index:2000;">
        <button onclick="cerrarPopup()" style="float:right; background:none; color:#6b7280; padding:2px 8px; font-size:16px;">✕</button>
        <h3 id="popupNombre" style="margin:0 0 6px;"></h3>
        <p id="popupDescripcion" style="margin:0 0 10px; font-size:14px; color:#4b5563;"></p>
        <img id="popupImagen" src="" style="width:100%; border-radius:6px; display:none;">
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/mapa.js"></script>
    <script src="assets/js/visor.js"></script>
</body>
</html>
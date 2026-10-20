let mapa = null;
let capaImagen = null;
let capaPines = L.layerGroup();
let capaRuta = L.layerGroup();

let pisos = [];
let pisoActualId = null;
let escalasPorPiso = {};
let rutaPorPiso = {};
let tramosEvitados = [];

document.addEventListener('DOMContentLoaded', async () => {
    await cargarPisos();
    escalasPorPiso = await precalcularEscalasPorPiso(pisos, 'uploads/pisos/');
    await cargarSelectorDeUbicaciones();

    document.getElementById('selectorPiso').addEventListener('change', (e) => {
        mostrarPiso(parseInt(e.target.value, 10));
    });
    document.getElementById('btnCalcularRuta').addEventListener('click', () => {
        tramosEvitados = [];
        calcularRuta();
    });
    document.getElementById('btnLimpiarRuta').addEventListener('click', limpiarRuta);
});

async function cargarPisos() {
    const respuesta = await fetch('api/pisos.php');
    pisos = await respuesta.json();

    const selector = document.getElementById('selectorPiso');
    selector.innerHTML = pisos.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('');

    if (pisos.length > 0) {
        mostrarPiso(pisos[0].id);
    }
}

async function cargarSelectorDeUbicaciones() {
    const ubicaciones = await (await fetch('api/ubicaciones.php')).json();
    const nodos = await (await fetch('api/nodos.php')).json();
    const entradas = nodos.filter(n => n.tipo === 'entrada');

    const opcionesUbicaciones = ubicaciones.map(u => `<option value="u${u.id}">${u.nombre} (${u.piso_nombre})</option>`).join('');
    const opcionesEntradas = entradas.map(n => `<option value="n${n.id}">🚪 ${n.etiqueta || 'Entrada'} (${n.piso_nombre})</option>`).join('');

    document.getElementById('selectorOrigen').innerHTML += opcionesEntradas + opcionesUbicaciones;
    document.getElementById('selectorDestino').innerHTML += opcionesUbicaciones;
}

async function mostrarPiso(pisoId) {
    pisoActualId = pisoId;
    const piso = pisos.find(p => p.id === pisoId);
    if (!piso) return;

    document.getElementById('selectorPiso').value = pisoId;

    if (!mapa) {
        mapa = await crearMapaDePiso('mapa', 'uploads/pisos/' + piso.imagen);
        capaPines.addTo(mapa);
        capaRuta.addTo(mapa);
    } else {
        capaImagen = await cambiarImagenDePiso(mapa, capaImagen, 'uploads/pisos/' + piso.imagen);
    }

    await cargarPinesDelPiso(pisoId);
    dibujarTramoDeRutaEnPisoActual();
}

async function cargarPinesDelPiso(pisoId) {
    capaPines.clearLayers();
    const respuesta = await fetch('api/ubicaciones.php?piso_id=' + pisoId);
    const ubicaciones = await respuesta.json();

    ubicaciones.forEach(u => {
        const marcador = L.marker(pctALatLng(u.coord_x, u.coord_y), {
            icon: iconoUbicacion(u.categoria_nombre),
        });
        marcador.on('click', () => mostrarPopup(u.nombre, u.descripcion, u.imagen_referencia));
        marcador.addTo(capaPines);
    });
}

async function calcularRuta(pidiendoOtra = false) {
    const origen = document.getElementById('selectorOrigen').value;
    const destino = document.getElementById('selectorDestino').value;
    const cajaInfo = document.getElementById('infoRuta');

    if (!origen || !destino) {
        alert('Elige un punto de partida y un destino');
        return;
    }

    const otraParam = pidiendoOtra ? '&otra=1' : '';
    const url = `api/ruta.php?origen=${origen}&destino=${destino}&evitar=${tramosEvitados.join(',')}${otraParam}`;
    const datos = await (await fetch(url)).json();

    if (datos.error) {
        cajaInfo.style.display = 'block';
        cajaInfo.innerHTML = `
            <strong>⚠️ ${datos.error}</strong>
            ${pidiendoOtra ? '<br><em>No queda otra ruta distinta disponible.</em>' : ''}
        `;
        return;
    }

    if (datos.tramo_nuevo_evitado) {
        tramosEvitados = [...tramosEvitados, datos.tramo_nuevo_evitado];
    }

    rutaPorPiso = {};
    datos.puntos.forEach(punto => {
        if (!rutaPorPiso[punto.piso_id]) rutaPorPiso[punto.piso_id] = [];
        rutaPorPiso[punto.piso_id].push(pctALatLng(punto.x, punto.y, escalasPorPiso[punto.piso_id]));
    });

    const pisosEnRuta = [...new Set(datos.puntos.map(p => p.piso_id))];
    const nombresPisos = pisosEnRuta.map(id => pisos.find(p => p.id === id)?.nombre || id);

    cajaInfo.style.display = 'block';
    cajaInfo.innerHTML = `
        <strong>Distancia total:</strong> ${datos.distancia_total} u<br>
        <strong>Itinerario:</strong> ${nombresPisos.join(' → ')}
        ${pisosEnRuta.length > 1 ? '<br><em>Esta ruta cruza de piso por una escalera marcada en el plano.</em>' : ''}
        <br><button id="btnOtraRuta" class="secundario" style="margin-top:8px;">🚧 No puedo pasar por aquí, buscar otra</button>
    `;
    document.getElementById('btnOtraRuta').addEventListener('click', () => calcularRuta(true));

    mostrarPiso(pisosEnRuta[0]);
}

function dibujarTramoDeRutaEnPisoActual() {
    capaRuta.clearLayers();
    const puntos = rutaPorPiso[pisoActualId];
    if (puntos && puntos.length > 1) {
        L.polyline(puntos, { color: '#dc2626', weight: 5, opacity: 0.85 }).addTo(capaRuta);
    }
}

function limpiarRuta() {
    rutaPorPiso = {};
    tramosEvitados = [];
    capaRuta.clearLayers();
    document.getElementById('infoRuta').style.display = 'none';
    document.getElementById('selectorOrigen').value = '';
    document.getElementById('selectorDestino').value = '';
}

function mostrarPopup(nombre, descripcion, urlImagen) {
    document.getElementById('popupNombre').textContent = nombre;
    document.getElementById('popupDescripcion').textContent = descripcion || '';
    const img = document.getElementById('popupImagen');
    img.style.display = urlImagen ? 'block' : 'none';
    img.src = urlImagen || '';
    document.getElementById('popupUbicacion').style.display = 'block';
}

function cerrarPopup() {
    document.getElementById('popupUbicacion').style.display = 'none';
}
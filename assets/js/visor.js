let mapa = null;
let capaImagen = null;
let capaPines = L.layerGroup();
let capaRuta = L.layerGroup();

let pisos = [];
let pisoActualId = null;
let escalasPorPiso = {};
let rutaPorPiso = {};
let tramosEvitados = [];
let categoriaFiltro = 'todas';
let todosLosItems = [];
let origenSel = null;
let destinoSel = null;
let itemEnPopup = null;

document.addEventListener('DOMContentLoaded', async () => {
    await cargarItems();
    await cargarPisos();
    escalasPorPiso = await precalcularEscalasPorPiso(pisos, 'uploads/pisos/');
    await cargarFiltroCategorias();
    actualizarSeleccion();
    configurarBuscador();

    document.getElementById('selectorPiso').addEventListener('change', (e) => {
        mostrarPiso(parseInt(e.target.value, 10));
    });
    document.getElementById('btnLimpiarRuta').addEventListener('click', limpiarTodo);
});

function escapar(texto) {
    return String(texto ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function normalizar(texto) {
    return (texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

async function cargarPisos() {
    const respuesta = await fetch('api/pisos.php');
    pisos = await respuesta.json();

    const selector = document.getElementById('selectorPiso');
    selector.innerHTML = pisos.map(p => `<option value="${p.id_piso}">${p.nombre}</option>`).join('');

    if (pisos.length > 0) {
        mostrarPiso(pisos[0].id_piso);
    }
}

async function cargarItems() {
    const ubicaciones = await (await fetch('api/ubicaciones.php')).json();
    const nodos = await (await fetch('api/nodos.php')).json();

    const lugares = ubicaciones.map(u => ({
        id: 'u' + u.id_ubicacion,
        tipo: 'ubicacion',
        nombre: u.nombre,
        descripcion: u.descripcion,
        imagen: u.imagen_referencia,
        id_piso: parseInt(u.id_piso, 10),
        piso_nombre: u.piso_nombre,
        x: parseFloat(u.coord_x),
        y: parseFloat(u.coord_y),
        id_categoria: u.id_categoria,
        categoria_nombre: u.categoria_nombre,
    }));

    const entradas = nodos.filter(n => n.tipo === 'entrada').map(n => ({
        id: 'n' + n.id_nodo,
        tipo: 'entrada',
        nombre: n.etiqueta || 'Entrada',
        descripcion: '',
        imagen: n.imagen_referencia,
        id_piso: parseInt(n.id_piso, 10),
        piso_nombre: n.piso_nombre,
        x: parseFloat(n.coord_x),
        y: parseFloat(n.coord_y),
    }));

    todosLosItems = [...lugares, ...entradas];
}

async function mostrarPiso(pisoId) {
    pisoActualId = pisoId;
    const piso = pisos.find(p => p.id_piso === pisoId);
    if (!piso) return;

    document.getElementById('selectorPiso').value = pisoId;

    if (!mapa) {
        mapa = await crearMapaDePiso('mapa', 'uploads/pisos/' + piso.imagen);
        capaPines.addTo(mapa);
        capaRuta.addTo(mapa);
    } else {
        capaImagen = await cambiarImagenDePiso(mapa, capaImagen, 'uploads/pisos/' + piso.imagen);
    }

    dibujarPines();
    dibujarTramoDeRutaEnPisoActual();
}

function iconoEntrada(claseExtra = '') {
    return L.divIcon({
        className: 'pin-ubicacion ' + claseExtra,
        html: '<span>🚪</span>',
        iconSize: [30, 30],
        iconAnchor: [15, 28],
        popupAnchor: [0, -26],
    });
}

function esSeleccionado(item) {
    return (origenSel && origenSel.id === item.id) || (destinoSel && destinoSel.id === item.id);
}

function coincideConFiltro(item) {
    if (categoriaFiltro === 'todas') return true;
    if (categoriaFiltro === 'ninguna') return false;
    if (item.tipo === 'entrada') return true;
    return String(item.id_categoria) === categoriaFiltro;
}

function dibujarPines() {
    capaPines.clearLayers();

    todosLosItems
        .filter(i => i.id_piso === pisoActualId && (esSeleccionado(i) || coincideConFiltro(i)))
        .forEach(item => {
            let clase = '';
            if (origenSel && origenSel.id === item.id) clase = 'pin-origen';
            if (destinoSel && destinoSel.id === item.id) clase = 'pin-destino';

            const icono = item.tipo === 'entrada'
                ? iconoEntrada(clase)
                : iconoUbicacion(item.categoria_nombre, clase);

            const marcador = L.marker(pctALatLng(item.x, item.y), { icon: icono });
            marcador.on('click', () => mostrarPopup(item));
            marcador.addTo(capaPines);
        });
}

function marcarOrigen(item) {
    if (origenSel && origenSel.id === item.id) {
        origenSel = null;
    } else {
        origenSel = item;
        if (destinoSel && destinoSel.id === item.id) destinoSel = null;
    }
    seleccionCambio();
}

function marcarDestino(item) {
    if (destinoSel && destinoSel.id === item.id) {
        destinoSel = null;
    } else {
        destinoSel = item;
        if (origenSel && origenSel.id === item.id) origenSel = null;
    }
    seleccionCambio();
}

function quitarOrigen() {
    origenSel = null;
    seleccionCambio();
}

function quitarDestino() {
    destinoSel = null;
    seleccionCambio();
}

function limpiarTodo() {
    origenSel = null;
    destinoSel = null;
    seleccionCambio();
}

function seleccionCambio() {
    cerrarPopup();
    actualizarSeleccion();
    dibujarPines();

    if (origenSel && destinoSel) {
        tramosEvitados = [];
        calcularRuta();
    } else {
        borrarRuta();
    }
}

function actualizarSeleccion() {
    const partes = [];

    if (origenSel) {
        partes.push(`<span class="chip chip-origen">📍 Desde: <b>${escapar(origenSel.nombre)}</b> <button onclick="quitarOrigen()" title="Quitar">✕</button></span>`);
    }
    if (destinoSel) {
        partes.push(`<span class="chip chip-destino">🏁 Hacia: <b>${escapar(destinoSel.nombre)}</b> <button onclick="quitarDestino()" title="Quitar">✕</button></span>`);
    }

    if (!origenSel && !destinoSel) {
        partes.push('<span class="ayuda">Toca un punto del mapa o usa el buscador</span>');
    } else if (!origenSel) {
        partes.push('<span class="ayuda">Falta elegir dónde estás</span>');
    } else if (!destinoSel) {
        partes.push('<span class="ayuda">Falta elegir a dónde ir</span>');
    }

    document.getElementById('seleccion').innerHTML = partes.join('');
}

function borrarRuta() {
    rutaPorPiso = {};
    tramosEvitados = [];
    capaRuta.clearLayers();
    document.getElementById('infoRuta').style.display = 'none';
}

async function calcularRuta(pidiendoOtra = false) {
    const cajaInfo = document.getElementById('infoRuta');
    if (!origenSel || !destinoSel) return;

    const otraParam = pidiendoOtra ? '&otra=1' : '';
    const url = `api/ruta.php?origen=${origenSel.id}&destino=${destinoSel.id}&evitar=${tramosEvitados.join(',')}${otraParam}`;
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
    let pisoAnterior = null;
    datos.puntos.forEach(punto => {
        if (!rutaPorPiso[punto.id_piso]) rutaPorPiso[punto.id_piso] = [];
        const tramos = rutaPorPiso[punto.id_piso];
        if (punto.id_piso !== pisoAnterior) tramos.push([]);
        tramos[tramos.length - 1].push(pctALatLng(punto.x, punto.y, escalasPorPiso[punto.id_piso]));
        pisoAnterior = punto.id_piso;
    });

    const pisosEnRuta = [...new Set(datos.puntos.map(p => p.id_piso))];
    const nombresPisos = pisosEnRuta.map(id => pisos.find(p => p.id_piso === id)?.nombre || id);

    cajaInfo.style.display = 'block';
    cajaInfo.innerHTML = `
        <strong>Itinerario:</strong> ${nombresPisos.join(' → ')}
        <br><button id="btnOtraRuta" class="secundario" style="margin-top:8px;">🚧 No puedo pasar por aquí, buscar otra</button>
    `;
    document.getElementById('btnOtraRuta').addEventListener('click', () => calcularRuta(true));

    mostrarPiso(pisosEnRuta[0]);
}

function dibujarTramoDeRutaEnPisoActual() {
    capaRuta.clearLayers();
    (rutaPorPiso[pisoActualId] || []).forEach(puntos => {
        if (puntos.length > 1) {
            L.polyline(puntos, { color: '#dc2626', weight: 5, opacity: 0.85 }).addTo(capaRuta);
        }
    });
}

function mostrarPopup(item) {
    itemEnPopup = item;
    document.getElementById('popupNombre').textContent = item.nombre;
    document.getElementById('popupDescripcion').textContent = item.descripcion || '';

    const img = document.getElementById('popupImagen');
    img.style.display = item.imagen ? 'block' : 'none';
    img.src = item.imagen || '';

    const esOrigen = origenSel && origenSel.id === item.id;
    const esDestino = destinoSel && destinoSel.id === item.id;
    document.getElementById('popupAcciones').innerHTML = `
        <button onclick="marcarOrigen(itemEnPopup)" class="${esOrigen ? 'secundario' : ''}">${esOrigen ? '✕ Quitar "Estoy aquí"' : '📍 Estoy aquí'}</button>
        <button onclick="marcarDestino(itemEnPopup)" class="${esDestino ? 'secundario' : ''}">${esDestino ? '✕ Quitar "Quiero ir aquí"' : '🏁 Quiero ir aquí'}</button>
    `;

    document.getElementById('popupUbicacion').style.display = 'block';
}

function cerrarPopup() {
    document.getElementById('popupUbicacion').style.display = 'none';
}

function configurarBuscador() {
    const campo = document.getElementById('buscador');
    const lista = document.getElementById('resultadosBusqueda');

    const actualizarLista = () => {
        const consulta = normalizar(campo.value.trim());
        if (!consulta) {
            lista.style.display = 'none';
            return;
        }

        const coincidencias = todosLosItems.filter(i => normalizar(i.nombre).includes(consulta)).slice(0, 8);
        lista.innerHTML = coincidencias.length
            ? coincidencias.map(i => `<div data-id="${i.id}">${i.tipo === 'entrada' ? '🚪 ' : ''}${escapar(i.nombre)} <span class="ayuda">(${escapar(i.piso_nombre)})</span></div>`).join('')
            : '<div class="ayuda">Sin resultados</div>';
        lista.style.display = 'block';
    };

    campo.addEventListener('input', actualizarLista);
    campo.addEventListener('focus', actualizarLista);

    lista.addEventListener('click', (e) => {
        const fila = e.target.closest('div[data-id]');
        if (!fila) return;
        const item = todosLosItems.find(i => i.id === fila.dataset.id);
        campo.value = '';
        lista.style.display = 'none';
        irAItem(item);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.buscador')) lista.style.display = 'none';
    });
}

async function irAItem(item) {
    if (item.id_piso !== pisoActualId) await mostrarPiso(item.id_piso);
    mapa.setView(pctALatLng(item.x, item.y), Math.min(mapa.getMaxZoom(), mapa.getZoom() + 1));
    mostrarPopup(item);
}

async function cargarFiltroCategorias() {
    const categorias = await (await fetch('api/categorias.php')).json();
    const selector = document.getElementById('filtroUbicaciones');
    selector.innerHTML = '<option value="todas">Todas</option><option value="ninguna">Ninguna</option>' +
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    selector.addEventListener('change', (e) => {
        categoriaFiltro = e.target.value;
        if (mapa) dibujarPines();
    });
}
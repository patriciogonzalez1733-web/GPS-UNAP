const fetchOriginal = window.fetch.bind(window);
window.fetch = async (...args) => {
    const respuesta = await fetchOriginal(...args);
    if (respuesta.status === 401) {
        location.href = 'login.php';
    }
    return respuesta;
};

let mapa = null;
let capaImagen = null;
const capaNodos = L.layerGroup();
const capaConexiones = L.layerGroup();
const capaUbicaciones = L.layerGroup();

let pisos = [];
let pisoActualId = null;
let categorias = [];
let nodos = [];
let conexiones = [];
let ubicaciones = [];

let modoActual = 'ver';
let nodoSeleccionadoParaConexion = null;
let coordPendiente = null;
let nodoAMover = null;
let filtroTipoNodo = '';
let textoBusquedaNodo = '';

const COLOR_TIPO_NODO = { pasillo: '#6b7280', entrada: '#059669', escalera: '#d97706' };

document.addEventListener('DOMContentLoaded', async () => {
    categorias = await (await fetch('../api/categorias.php')).json();

    const respuestaPisos = await fetch('../api/pisos.php');
    pisos = await respuestaPisos.json();
    const selectorPiso = document.getElementById('selectorPiso');
    selectorPiso.innerHTML = pisos.map(p => `<option value="${p.id_piso}">${p.nombre}</option>`).join('');
    selectorPiso.addEventListener('change', (e) => cargarPiso(parseInt(e.target.value, 10)));

    if (pisos.length > 0) {
        await cargarPiso(pisos[0].id_piso);
    } else {
        alert('Primero sube al menos un piso en "Administrar pisos".');
    }

    document.getElementById('modoVer').addEventListener('click', () => cambiarModo('ver'));
    document.getElementById('modoNodo').addEventListener('click', () => cambiarModo('nodo'));
    document.getElementById('modoConectar').addEventListener('click', () => cambiarModo('conectar'));
    document.getElementById('modoUbicacion').addEventListener('click', () => cambiarModo('ubicacion'));

    document.getElementById('filtroTipoNodo').addEventListener('change', (e) => {
        filtroTipoNodo = e.target.value;
        actualizarListaNodos();
    });
    document.getElementById('buscadorNodos').addEventListener('input', (e) => {
        textoBusquedaNodo = e.target.value.trim().toLowerCase();
        actualizarListaNodos();
    });
    actualizarCursorCruz();
});

function actualizarCursorCruz(){
    const marcando = nodoAMover !== null || modoActual === 'nodo' || modoActual === 'ubicacion' || modoActual === 'conectar' 
    || modoActual === 'ver';
    document.getElementById('mapa').classList.toggle('cursor-cruz', marcando);
}

function cambiarModo(nuevoModo) {
    modoActual = nuevoModo;
    nodoSeleccionadoParaConexion = null;
    nodoAMover = null;
    document.getElementById('formPendiente').style.display = 'none';

    document.querySelectorAll('.modos button').forEach(b => b.classList.remove('modo-activo'));
    document.getElementById('modo' + nuevoModo.charAt(0).toUpperCase() + nuevoModo.slice(1)).classList.add('modo-activo');

    actualizarCursorCruz();
    dibujarNodos();
}

async function cargarPiso(pisoId) {
    pisoActualId = pisoId;
    const piso = pisos.find(p => p.id_piso === pisoId);
    document.getElementById('selectorPiso').value = pisoId;

    if (!mapa) {
        mapa = await crearMapaDePiso('mapa', '../uploads/pisos/' + piso.imagen, { maxZoom: 7 });
        capaNodos.addTo(mapa);
        capaConexiones.addTo(mapa);
        capaUbicaciones.addTo(mapa);
        mapa.on('click', alHacerClicEnElMapa);
    } else {
        capaImagen = await cambiarImagenDePiso(mapa, capaImagen, '../uploads/pisos/' + piso.imagen);
    }

    await Promise.all([cargarNodos(), cargarConexiones(), cargarUbicaciones()]);
}

async function cargarNodos() {
    nodos = await (await fetch('../api/nodos.php?id_piso=' + pisoActualId)).json();
    dibujarNodos();
    actualizarListaNodos();
}

async function cargarConexiones() {
    conexiones = await (await fetch('../api/conexiones.php?id_piso=' + pisoActualId)).json();
    dibujarConexiones();
}

async function cargarUbicaciones() {
    ubicaciones = await (await fetch('../api/ubicaciones.php?id_piso=' + pisoActualId)).json();
    dibujarUbicaciones();
    actualizarListaUbicaciones();
}


function dibujarNodos() {
    capaNodos.clearLayers();
    nodos.forEach(n => {
        const esSeleccionado = n.id_nodo === nodoSeleccionadoParaConexion;
        const circulo = L.circleMarker(pctALatLng(n.coord_x, n.coord_y), {
            radius: esSeleccionado ? 10 : 7,
            color: esSeleccionado ? '#111827' : COLOR_TIPO_NODO[n.tipo],
            weight: esSeleccionado ? 3 : 2,
            fillColor: COLOR_TIPO_NODO[n.tipo],
            fillOpacity: 0.9,
        });
        circulo.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            alHacerClicEnNodo(n);
        });

        circulo.bindTooltip('#' + n.id_nodo, { permanent: true, direction: 'top', offset: [0, -6], className: 'etiqueta-nodo' });
        circulo.addTo(capaNodos);
    });
}

function dibujarConexiones() {
    capaConexiones.clearLayers();
    const nodosPorId = Object.fromEntries(nodos.map(n => [n.id_nodo, n]));

    const yaDibujadas = new Set();
    conexiones.forEach(c => {
        const clave = [c.id_nodo_origen, c.id_nodo_destino].sort().join('-');
        if (yaDibujadas.has(clave)) return;
        yaDibujadas.add(clave);

        const origen = nodosPorId[c.id_nodo_origen];
        const destino = nodosPorId[c.id_nodo_destino];
        if (!origen || !destino) return;

        const linea = L.polyline([pctALatLng(origen.coord_x, origen.coord_y), pctALatLng(destino.coord_x, destino.coord_y)], {
            color: '#3b82f6', weight: 3, opacity: 0.7,
        });
        linea.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            if (modoActual !== 'ver') return;
            if (confirm(`¿Eliminar esta conexión? (distancia: ${parseFloat(c.distancia).toFixed(1)})`)) {
                fetch('../api/conexiones.php?id_conexion=' + c.id_conexion, { method: 'DELETE' }).then(cargarConexiones);
            }
        });
        linea.addTo(capaConexiones);
    });
}

function dibujarUbicaciones() {
    capaUbicaciones.clearLayers();
    ubicaciones.forEach(u => {
        const marcador = L.marker(pctALatLng(u.coord_x, u.coord_y), { icon: iconoUbicacion(u.categoria_nombre) });
        marcador.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            if (modoActual === 'ver') mostrarFormularioUbicacion(u);
        });
        marcador.bindTooltip(u.nombre);
        marcador.addTo(capaUbicaciones);
    });
}

async function alHacerClicEnElMapa(e) {
    const { x, y } = latLngAPct(e.latlng);

    if (nodoAMover !== null) {
        await fetch('../api/nodos.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_nodo: nodoAMover, coord_x: x, coord_y: y }),
        });
        nodoAMover = null;
        actualizarCursorCruz()
        document.getElementById('formPendiente').style.display = 'none';
        cargarNodos();
        cargarConexiones();
        return;
    }

    coordPendiente = { x, y };

    if (modoActual === 'nodo') mostrarFormularioNodo();
    if (modoActual === 'ubicacion') mostrarFormularioUbicacion();
}

function alHacerClicEnNodo(nodo) {
    if (modoActual === 'ver') {
        mostrarFormularioNodo(nodo);
        return;
    }
    if (modoActual !== 'conectar') return;

    if (nodoSeleccionadoParaConexion === null) {
        nodoSeleccionadoParaConexion = nodo.id_nodo;
        dibujarNodos();
        mostrarFormularioConexionEntrePisos();
        return;
    }

    if (nodoSeleccionadoParaConexion === nodo.id_nodo) {
        nodoSeleccionadoParaConexion = null;
        dibujarNodos();
        document.getElementById('formPendiente').style.display = 'none';
        return;
    }

    fetch('../api/conexiones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_nodo_origen: nodoSeleccionadoParaConexion, id_nodo_destino: nodo.id_nodo }),
    }).then(() => {
        nodoSeleccionadoParaConexion = null;
        document.getElementById('formPendiente').style.display = 'none';
        cargarConexiones();
        dibujarNodos();
    });
}

async function mostrarFormularioConexionEntrePisos() {
    const todosLosNodos = await (await fetch('../api/nodos.php')).json();
    const nodoOrigen = todosLosNodos.find(n => n.id_nodo === nodoSeleccionadoParaConexion);
    const candidatos = todosLosNodos.filter(n => n.id_piso !== pisoActualId);

    const contenedor = document.getElementById('formPendiente');
    contenedor.style.display = 'block';
    contenedor.innerHTML = `
        <strong>Pasillo #${nodoOrigen.id_nodo} seleccionado</strong>
        <p style="font-size:13px;color:#555;">Haz clic en otro pasillo de este mismo piso para conectarlo,
        o elige uno de otro piso (ej: para una escalera):</p>
        <div class="campo">
            <select id="campoNodoOtroPiso">
                <option value="">-- Elegir pasillo de otro piso --</option>
                ${candidatos.map(n => `<option value="${n.id_nodo}">${n.piso_nombre}: #${n.id_nodo} ${n.etiqueta || n.tipo}</option>`).join('')}
            </select>
        </div>
        <button onclick="confirmarConexionEntrePisos()">Conectar</button>
        <button class="secundario" onclick="cambiarModo('conectar')">Cancelar</button>
    `;
}

async function confirmarConexionEntrePisos() {
    const destinoId = document.getElementById('campoNodoOtroPiso').value;
    if (!destinoId) { alert('Elige un pasillo de otro piso'); return; }

    await fetch('../api/conexiones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_nodo_origen: nodoSeleccionadoParaConexion,
            id_nodo_destino: parseInt(destinoId, 10),
            distancia: 15,
        }),
    });

    nodoSeleccionadoParaConexion = null;
    document.getElementById('formPendiente').style.display = 'none';
    cargarConexiones();
    dibujarNodos();
}

function mostrarFormularioNodo(nodoExistente = null) {
    const esEdicion = nodoExistente !== null;
    const tipoActual = esEdicion ? nodoExistente.tipo : 'pasillo';
    const fotoActual = esEdicion ? nodoExistente.imagen_referencia : null;
    const contenedor = document.getElementById('formPendiente');
    contenedor.style.display = 'block';
    contenedor.innerHTML = `
        <strong>${esEdicion ? 'Editar nodo #' + nodoExistente.id_nodo : 'Nuevo pasillo'}</strong>
        <div class="campo">
            <label>Tipo</label>
            <select id="campoTipoNodo" onchange="alternarFotoNodo()">
                <option value="pasillo" ${tipoActual === 'pasillo' ? 'selected' : ''}>Pasillo</option>
                <option value="entrada" ${tipoActual === 'entrada' ? 'selected' : ''}>Entrada</option>
                <option value="escalera" ${tipoActual === 'escalera' ? 'selected' : ''}>Escalera</option>
            </select>
        </div>
        <div class="campo">
            <label>Etiqueta (opcional)</label>
            <input type="text" id="campoEtiquetaNodo" placeholder="Ej: Escalera ala norte" value="${esEdicion ? (nodoExistente.etiqueta || '').replace(/"/g, '&quot;') : ''}">
        </div>
        <div class="campo" id="contenedorFotoNodo" style="display:${tipoActual === 'entrada' ? 'block' : 'none'};">
            <label>Foto de referencia (opcional)</label>
            ${fotoActual ? `
                <img src="../${fotoActual}" style="width:100%; border-radius:6px; margin-bottom:6px;">
                <label style="font-weight:normal;"><input type="checkbox" id="campoQuitarFotoNodo" style="width:auto;"> Quitar foto actual</label>
            ` : ''}
            <input type="file" id="campoFotoNodo" accept="image/png,image/jpeg,image/webp">
        </div>
        <button onclick="confirmarNodo(${esEdicion ? nodoExistente.id_nodo : 'null'})">Guardar</button>
        <button class="secundario" onclick="document.getElementById('formPendiente').style.display='none'">Cancelar</button>
        ${esEdicion ? `
            <button class="secundario" onclick="iniciarMoverNodo(${nodoExistente.id_nodo})">Mover</button>
            <button class="peligro" onclick="eliminarNodo(${nodoExistente.id_nodo})">Eliminar</button>
        ` : ''}
    `;
}

function alternarFotoNodo() {
    const visible = document.getElementById('campoTipoNodo').value === 'entrada';
    document.getElementById('contenedorFotoNodo').style.display = visible ? 'block' : 'none';
}

function iniciarMoverNodo(id) {
    nodoAMover = id;
    actualizarCursorCruz();
    document.getElementById('formPendiente').innerHTML = `
        <strong>Mover nodo #${id}</strong>
        <p style="font-size:13px;color:#555;">Haz clic en el mapa donde quieres dejarlo.</p>
        <button class="secundario" onclick="cambiarModo('ver')">Cancelar</button>
    `;
}

async function confirmarNodo(idExistente) {
    const tipo = document.getElementById('campoTipoNodo').value;
    const etiqueta = document.getElementById('campoEtiquetaNodo').value.trim() || null;
    const archivo = document.getElementById('campoFotoNodo').files[0];
    const casillaQuitar = document.getElementById('campoQuitarFotoNodo');
    const quitarFoto = casillaQuitar !== null && casillaQuitar.checked;

    let idNodo = idExistente;

    if (idExistente) {
        await fetch('../api/nodos.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_nodo: idExistente, tipo, etiqueta }),
        });
    } else {
        const respuesta = await fetch('../api/nodos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_piso: pisoActualId, tipo, etiqueta, coord_x: coordPendiente.x, coord_y: coordPendiente.y }),
        });
        idNodo = (await respuesta.json()).id_nodo;
    }

    if (tipo === 'entrada') {
        if (archivo) {
            const formulario = new FormData();
            formulario.append('id_nodo', idNodo);
            formulario.append('imagen', archivo);
            const resultado = await (await fetch('../api/nodos_imagen.php', { method: 'POST', body: formulario })).json();
            if (resultado.error) alert('El nodo se guardó, pero la foto no: ' + resultado.error);
        } else if (quitarFoto) {
            await fetch('../api/nodos_imagen.php?id_nodo=' + idNodo, { method: 'DELETE' });
        }
    }

    document.getElementById('formPendiente').style.display = 'none';
    cargarNodos();
}

function editarNodoPorId(id) {
    const nodo = nodos.find(n => n.id_nodo === id);
    if (!nodo) return;
    mapa.setView(pctALatLng(nodo.coord_x, nodo.coord_y), Math.max(mapa.getZoom(), 3));
    cambiarModo('ver');
    mostrarFormularioNodo(nodo);
}

function mostrarFormularioUbicacion(ubicacionExistente = null) {
    const esEdicion = ubicacionExistente !== null;
    const fotoActual = esEdicion ? ubicacionExistente.imagen_referencia : null;
    const contenedor = document.getElementById('formPendiente');
    contenedor.style.display = 'block';
    contenedor.innerHTML = `
        <strong>${esEdicion ? 'Editar ubicación' : 'Nueva ubicación'}</strong>
        <div class="campo">
            <label>Nombre</label>
            <input type="text" id="campoNombreUbic" placeholder="Ej: Sala 204" value="${esEdicion ? ubicacionExistente.nombre : ''}">
        </div>
        <div class="campo">
            <label>Categoría</label>
            <select id="campoCategoriaUbic">
                ${categorias.map(c => `<option value="${c.id_categoria}" ${esEdicion && c.id_categoria === ubicacionExistente.id_categoria ? 'selected' : ''}>${c.nombre}</option>`).join('')}
            </select>
        </div>
        <div class="campo">
            <label>Descripción (opcional)</label>
            <textarea id="campoDescripcionUbic" rows="2">${esEdicion ? (ubicacionExistente.descripcion || '') : ''}</textarea>
        </div>
        <div class="campo">
            <label>Pasillo más cercano (para calcular rutas)</label>
            <select id="campoNodoCercanoUbic">
                <option value="">-- Ninguno --</option>
                ${nodos.map(n => `<option value="${n.id_nodo}" ${esEdicion && n.id_nodo === ubicacionExistente.id_nodo_cercano ? 'selected' : ''}>#${n.id_nodo} ${n.etiqueta || n.tipo}</option>`).join('')}
            </select>
        </div>
        <div class="campo">
            <label>Foto de referencia (opcional)</label>
            ${fotoActual ? `
                <img src="../${fotoActual}" style="width:100%; border-radius:6px; margin-bottom:6px;">
                <label style="font-weight:normal;"><input type="checkbox" id="campoQuitarFoto" style="width:auto;"> Quitar foto actual</label>
            ` : ''}
            <input type="file" id="campoFotoUbic" accept="image/png,image/jpeg,image/webp">
        </div>
        <button onclick="confirmarUbicacion(${esEdicion ? ubicacionExistente.id_ubicacion : 'null'})">Guardar</button>
        <button class="secundario" onclick="document.getElementById('formPendiente').style.display='none'">Cancelar</button>
        ${esEdicion ? `<button class="peligro" onclick="eliminarUbicacion(${ubicacionExistente.id_ubicacion})">Eliminar</button>` : ''}
    `;
}

async function confirmarUbicacion(idExistente) {
    const nombre = document.getElementById('campoNombreUbic').value.trim();
    if (!nombre) { alert('El nombre es obligatorio'); return; }

    const archivo = document.getElementById('campoFotoUbic').files[0];
    const casillaQuitar = document.getElementById('campoQuitarFoto');
    const quitarFoto = casillaQuitar !== null && casillaQuitar.checked;

    const datos = {
        id_piso: pisoActualId,
        id_categoria: document.getElementById('campoCategoriaUbic').value,
        nombre,
        descripcion: document.getElementById('campoDescripcionUbic').value.trim(),
        id_nodo_cercano: document.getElementById('campoNodoCercanoUbic').value || null,
    };

    let idUbicacion = idExistente;

    if (idExistente) {
        const actual = ubicaciones.find(u => u.id_ubicacion === idExistente);
        datos.id_ubicacion = idExistente;
        datos.coord_x = actual.coord_x;
        datos.coord_y = actual.coord_y;
        await fetch('../api/ubicaciones.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(datos) });
    } else {
        datos.coord_x = coordPendiente.x;
        datos.coord_y = coordPendiente.y;
        const respuesta = await fetch('../api/ubicaciones.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(datos) });
        idUbicacion = (await respuesta.json()).id_ubicacion;
    }

    if (archivo) {
        const formulario = new FormData();
        formulario.append('id_ubicacion', idUbicacion);
        formulario.append('imagen', archivo);
        const resultado = await (await fetch('../api/ubicaciones_imagen.php', { method: 'POST', body: formulario })).json();
        if (resultado.error) alert('La ubicación se guardó, pero la foto no: ' + resultado.error);
    } else if (quitarFoto) {
        await fetch('../api/ubicaciones_imagen.php?id_ubicacion=' + idUbicacion, { method: 'DELETE' });
    }

    document.getElementById('formPendiente').style.display = 'none';
    cargarUbicaciones();
}

function actualizarListaNodos() {
    const visibles = nodos.filter(n => {
        if (filtroTipoNodo && n.tipo !== filtroTipoNodo) return false;
        if (!textoBusquedaNodo) return true;
        const texto = `#${n.id_nodo} ${n.etiqueta || ''} ${n.tipo}`.toLowerCase();
        return texto.includes(textoBusquedaNodo);
    });

    document.getElementById('contadorNodos').textContent = visibles.length === nodos.length ? nodos.length : `${visibles.length} de ${nodos.length}`;
    document.getElementById('listaNodos').innerHTML = visibles.map(n => `
        <div class="item-lista">
            <span style="cursor:pointer;" onclick="editarNodoPorId(${n.id_nodo})">
                <span class="badge ${n.tipo}">${n.tipo}</span> #${n.id_nodo} ${n.etiqueta || ''}
            </span>
            <button class="peligro" onclick="eliminarNodo(${n.id_nodo})">✕</button>
        </div>
    `).join('') || '<p style="color:#888;font-size:13px;">Ningún pasillo coincide.</p>';
}

function actualizarListaUbicaciones() {
    document.getElementById('contadorUbicaciones').textContent = ubicaciones.length;
    document.getElementById('listaUbicaciones').innerHTML = ubicaciones.map(u => `
        <div class="item-lista">
            <span style="cursor:pointer;" onclick="editarUbicacionPorId(${u.id_ubicacion})">
                ${u.nombre} <em style="color:#888;">(${u.categoria_nombre})</em>
                ${u.id_nodo_cercano ? '' : ' <span style="color:#b91c1c;">⚠ sin pasillo</span>'}
            </span>
            <button class="peligro" onclick="eliminarUbicacion(${u.id_ubicacion})">✕</button>
        </div>
    `).join('') || '<p style="color:#888;font-size:13px;">Sin ubicaciones en este piso.</p>';
}

function editarUbicacionPorId(id) {
    mostrarFormularioUbicacion(ubicaciones.find(u => u.id_ubicacion === id));
}

async function eliminarNodo(id) {
    if (!confirm('¿Eliminar este pasillo? También se borrarán sus conexiones.')) return;
    await fetch('../api/nodos.php?id_nodo=' + id, { method: 'DELETE' });
    document.getElementById('formPendiente').style.display = 'none';
    cargarNodos();
    cargarConexiones();
}

async function eliminarUbicacion(id) {
    if (!confirm('¿Eliminar esta ubicación?')) return;
    await fetch('../api/ubicaciones.php?id_ubicacion=' + id, { method: 'DELETE' });
    cargarUbicaciones();
}
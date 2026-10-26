let escalaAlturaActual = 1;

function limitesDelPisoActual() {
    return [[0, 0], [100 * escalaAlturaActual, 100]];
}

function pctALatLng(x, y, escala = escalaAlturaActual) {
    return L.latLng((100 - y) * escala, x);
}

function latLngAPct(latlng) {
    return { x: latlng.lng, y: 100 - (latlng.lat / escalaAlturaActual) };
}

function ajustarLimitesDelMapa(mapa) {
    const limites = limitesDelPisoActual();
    mapa.setMaxBounds(L.latLngBounds(limites).pad(0.1));
    mapa.setMinZoom(mapa.getBoundsZoom(limites));
}

function medirImagen(url) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve({ ancho: img.naturalWidth, alto: img.naturalHeight });
        img.onerror = () => resolve({ ancho: 100, alto: 100 });
        img.src = url;
    });
}

async function precalcularEscalasPorPiso(pisos, carpetaBase) {
    const entradas = await Promise.all(pisos.map(async (p) => {
        const { ancho, alto } = await medirImagen(carpetaBase + p.imagen);
        return [p.id_piso, alto / ancho];
    }));
    return Object.fromEntries(entradas);
}

async function crearMapaDePiso(idContenedor, urlImagen, opciones = {}) {
    const { ancho, alto } = await medirImagen(urlImagen);
    escalaAlturaActual = alto / ancho;

    const mapa = L.map(idContenedor, {
        crs: L.CRS.Simple,
        minZoom: -3,
        maxZoom: opciones.maxZoom || 4,
        zoomSnap: 0.25,
        maxBoundsViscosity: 1,
        attributionControl: false,
    });

    L.imageOverlay(urlImagen, limitesDelPisoActual()).addTo(mapa);

    const contenedor = document.getElementById(idContenedor);
    let usuarioInteractuo = false;
    ['pointerdown', 'wheel'].forEach((evento) => {
        contenedor.addEventListener(evento, () => { usuarioInteractuo = true; }, { passive: true });
    });

    new ResizeObserver((entradas) => {
        const { width, height } = entradas[0].contentRect;
        if (width === 0 || height === 0) return;
        mapa.invalidateSize();
        ajustarLimitesDelMapa(mapa);
        if (!usuarioInteractuo) mapa.fitBounds(limitesDelPisoActual(), { animate: false });
    }).observe(contenedor);

    return mapa;
}

async function cambiarImagenDePiso(mapa, capaImagenAnterior, urlImagen) {
    if (capaImagenAnterior) mapa.removeLayer(capaImagenAnterior);

    const { ancho, alto } = await medirImagen(urlImagen);
    escalaAlturaActual = alto / ancho;

    const nuevaCapa = L.imageOverlay(urlImagen, limitesDelPisoActual()).addTo(mapa);
    ajustarLimitesDelMapa(mapa);
    mapa.fitBounds(limitesDelPisoActual());
    return nuevaCapa;
}

const EMOJI_POR_CATEGORIA = {
    aula: '🏫',
    laboratorio: '🧪',
    oficina: '🗄️',
    'baño': '🚻',
    servicio: '📌',
};
function emojiDeCategoria(nombreCategoria) {
    const clave = (nombreCategoria || '').toLowerCase().trim();
    return EMOJI_POR_CATEGORIA[clave] || '📍';
}

function iconoUbicacion(nombreCategoria, claseExtra = '') {
    return L.divIcon({
        className: 'pin-ubicacion ' + claseExtra,
        html: `<span>${emojiDeCategoria(nombreCategoria)}</span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 28],
        popupAnchor: [0, -26],
    });
}
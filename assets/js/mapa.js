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
        return [p.id, alto / ancho];
    }));
    return Object.fromEntries(entradas);
}

async function crearMapaDePiso(idContenedor, urlImagen) {
    const { ancho, alto } = await medirImagen(urlImagen);
    escalaAlturaActual = alto / ancho;

    const mapa = L.map(idContenedor, {
        crs: L.CRS.Simple,
        minZoom: -3,
        maxZoom: 4,
        attributionControl: false,
    });

    L.imageOverlay(urlImagen, limitesDelPisoActual()).addTo(mapa);

    let yaEncuadrado = false;
    new ResizeObserver((entradas) => {
        const { width, height } = entradas[0].contentRect;
        mapa.invalidateSize();
        if (!yaEncuadrado && width > 0 && height > 0) {
            mapa.fitBounds(limitesDelPisoActual());
            yaEncuadrado = true;
        }
    }).observe(document.getElementById(idContenedor));

    return mapa;
}

async function cambiarImagenDePiso(mapa, capaImagenAnterior, urlImagen) {
    if (capaImagenAnterior) mapa.removeLayer(capaImagenAnterior);

    const { ancho, alto } = await medirImagen(urlImagen);
    escalaAlturaActual = alto / ancho;

    const nuevaCapa = L.imageOverlay(urlImagen, limitesDelPisoActual()).addTo(mapa);
    mapa.fitBounds(limitesDelPisoActual());
    return nuevaCapa;
}

const EMOJI_POR_CATEGORIA = {
    aula: '🏫', laboratorio: '🧪', oficina: '🗄️', 'baño': '🚻', servicio: 'ℹ️',
    biblioteca: '📚', cafeteria: '☕', escalera: '🪜', entrada: '🚪',
};

function emojiDeCategoria(nombreCategoria) {
    const clave = (nombreCategoria || '').toLowerCase().trim();
    return EMOJI_POR_CATEGORIA[clave] || '📍';
}

function iconoUbicacion(nombreCategoria) {
    return L.divIcon({
        className: 'pin-ubicacion',
        html: `<span>${emojiDeCategoria(nombreCategoria)}</span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 28],
        popupAnchor: [0, -26],
    });
}
<?php

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

function resolverExtremo(PDO $pdo, string $identificador): ?array
{
    $tipo = $identificador[0] ?? '';
    $id = (int) substr($identificador, 1);

    if ($tipo === 'u') {
        $stmt = $pdo->prepare('SELECT * FROM ubicaciones WHERE id = ?');
        $stmt->execute([$id]);
        $ubicacion = $stmt->fetch();

        if (!$ubicacion || !$ubicacion['nodo_cercano_id']) {
            return null;
        }

        return [
            'nodo_grafo_id' => (int) $ubicacion['nodo_cercano_id'],
            'piso_id' => (int) $ubicacion['piso_id'],
            'x' => (float) $ubicacion['coord_x'],
            'y' => (float) $ubicacion['coord_y'],
            'tipo_punto' => 'ubicacion',
        ];
    }

    if ($tipo === 'n') {
        $stmt = $pdo->prepare('SELECT * FROM nodos WHERE id = ?');
        $stmt->execute([$id]);
        $nodo = $stmt->fetch();

        if (!$nodo) {
            return null;
        }

        return [
            'nodo_grafo_id' => (int) $nodo['id'],
            'piso_id' => (int) $nodo['piso_id'],
            'x' => (float) $nodo['coord_x'],
            'y' => (float) $nodo['coord_y'],
            'tipo_punto' => $nodo['tipo'],
        ];
    }

    return null;
}

function dijkstra(array $adyacencia, int $inicio, int $fin): ?array
{
    $distancias = [$inicio => 0];
    $anterior = [];
    $visitados = [];

    $cola = new SplPriorityQueue();
    $cola->insert($inicio, 0);

    while (!$cola->isEmpty()) {
        $actual = $cola->extract();
        if (isset($visitados[$actual])) continue;
        $visitados[$actual] = true;

        if ($actual === $fin) break;

        foreach ($adyacencia[$actual] ?? [] as [$vecino, $peso]) {
            $nuevaDistancia = $distancias[$actual] + $peso;
            if (!isset($distancias[$vecino]) || $nuevaDistancia < $distancias[$vecino]) {
                $distancias[$vecino] = $nuevaDistancia;
                $anterior[$vecino] = $actual;
                $cola->insert($vecino, -$nuevaDistancia);
            }
        }
    }

    if (!isset($distancias[$fin])) {
        return null;
    }

    $camino = [$fin];
    $paso = $fin;
    while ($paso !== $inicio) {
        $paso = $anterior[$paso];
        $camino[] = $paso;
    }

    return ['camino' => array_reverse($camino), 'distancia' => $distancias[$fin]];
}

function construirAdyacencia(array $nodos, array $conexiones, array $tramosEvitados): array
{
    $adyacencia = [];
    foreach ($nodos as $n) {
        $adyacencia[$n['id']] = [];
    }
    foreach ($conexiones as $c) {
        $a = (int) $c['nodo_origen_id'];
        $b = (int) $c['nodo_destino_id'];
        if (isset($tramosEvitados[$a . '-' . $b])) continue;
        $adyacencia[$a][] = [$b, (float) $c['distancia']];
    }
    return $adyacencia;
}

$origen = resolverExtremo($pdo, $_GET['origen'] ?? '');
$destino = resolverExtremo($pdo, $_GET['destino'] ?? '');

if (!$origen || !$destino) {
    http_response_code(400);
    echo json_encode(['error' => 'Origen o destino inválido, o esa ubicación no tiene un pasillo cercano asignado']);
    exit;
}

$nodos = $pdo->query('SELECT * FROM nodos')->fetchAll();
$nodosPorId = [];
foreach ($nodos as $n) {
    $nodosPorId[$n['id']] = $n;
}
$conexiones = $pdo->query('SELECT * FROM conexiones')->fetchAll();

$tramosEvitados = [];
foreach (explode(',', $_GET['evitar'] ?? '') as $tramo) {
    if (!str_contains($tramo, '-')) continue;
    [$a, $b] = array_map('intval', explode('-', $tramo, 2));
    $tramosEvitados[$a . '-' . $b] = true;
    $tramosEvitados[$b . '-' . $a] = true;
}

$inicioGrafo = $origen['nodo_grafo_id'];
$finGrafo = $destino['nodo_grafo_id'];

function calcularCamino(int $inicio, int $fin, array $nodos, array $conexiones, array $tramosEvitados): ?array
{
    if ($inicio === $fin) {
        return ['camino' => [$inicio], 'distancia' => 0];
    }
    $adyacencia = construirAdyacencia($nodos, $conexiones, $tramosEvitados);
    return dijkstra($adyacencia, $inicio, $fin);
}

$resultado = calcularCamino($inicioGrafo, $finGrafo, $nodos, $conexiones, $tramosEvitados);
$tramoNuevoEvitado = null;

if ($resultado !== null && !empty($_GET['otra']) && count($resultado['camino']) > 1) {
    $camino = $resultado['camino'];
    $mejorCandidato = null;
    $mejorTramo = null;

    for ($i = 0; $i < count($camino) - 1; $i++) {
        $a = $camino[$i];
        $b = $camino[$i + 1];
        $clave = $a . '-' . $b;
        if (isset($tramosEvitados[$clave])) continue;

        $tramosDePrueba = $tramosEvitados;
        $tramosDePrueba[$a . '-' . $b] = true;
        $tramosDePrueba[$b . '-' . $a] = true;

        $candidato = calcularCamino($inicioGrafo, $finGrafo, $nodos, $conexiones, $tramosDePrueba);
        if ($candidato === null) continue;
        if ($candidato['camino'] === $camino) continue;

        if ($mejorCandidato === null || $candidato['distancia'] < $mejorCandidato['distancia']) {
            $mejorCandidato = $candidato;
            $mejorTramo = $clave;
        }
    }

    if ($mejorCandidato !== null) {
        $resultado = $mejorCandidato;
        $tramoNuevoEvitado = $mejorTramo;
    } else {
        $resultado = null;
    }
}

if ($resultado === null) {
    http_response_code(404);
    echo json_encode(['error' => 'No existe una ruta entre esos dos puntos']);
    exit;
}

$puntos = [];
$puntos[] = ['piso_id' => $origen['piso_id'], 'x' => $origen['x'], 'y' => $origen['y'], 'tipo' => $origen['tipo_punto']];

foreach ($resultado['camino'] as $nodoId) {
    $n = $nodosPorId[$nodoId];
    $puntos[] = ['piso_id' => (int) $n['piso_id'], 'x' => (float) $n['coord_x'], 'y' => (float) $n['coord_y'], 'tipo' => $n['tipo']];
}

$puntos[] = ['piso_id' => $destino['piso_id'], 'x' => $destino['x'], 'y' => $destino['y'], 'tipo' => $destino['tipo_punto']];

echo json_encode([
    'distancia_total' => round($resultado['distancia'], 2),
    'puntos' => $puntos,
    'tramo_nuevo_evitado' => $tramoNuevoEvitado,
]);
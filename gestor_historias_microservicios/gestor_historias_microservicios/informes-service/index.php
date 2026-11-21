<?php
/**
 * SERVICIO DE INFORMES · puerto 8003 (sin base de datos)
 *   GET /informes/general        resumen de todos los sprints
 *   GET /informes/sprint/{id}    resumen de un sprint
 *   GET /informes/health         estado del servicio
 * Calcula los reportes consultando por HTTP a los servicios de sprints e historias.
 */
require __DIR__ . '/lib.php';
$cfg = require __DIR__ . '/config.php';

// Estados conocidos (el servicio de historias es el dueño de esta lista)
const ESTADOS = ['nueva', 'activa', 'finalizada', 'impedimento'];

/** GET a otro servicio; si no responde devuelve 503 y si falla devuelve 502. */
function consultar(string $url, string $nombre, array $permitir = []): array
{
    [$codigo, $datos] = http_json('GET', $url);
    if ($codigo === 0) {
        json_out(503, ['error' => "El servicio de $nombre no está disponible."]);
    }
    if ($codigo === 404 && in_array(404, $permitir, true)) {
        json_out(404, ['error' => 'El sprint no existe.']);
    }
    if ($codigo !== 200 || !is_array($datos)) {
        json_out(502, ['error' => "Respuesta inesperada del servicio de $nombre."]);
    }
    return $datos;
}

function porcentaje(int $parte, int $total): int
{
    return $total > 0 ? (int)round($parte * 100 / $total) : 0;
}

/** Calcula resumen, historias por estado y carga por responsable. */
function armar(array $historias): array
{
    $resumen = ['historias' => count($historias), 'puntos' => 0, 'puntos_finalizados' => 0];
    $estados = [];
    foreach (ESTADOS as $e) {
        $estados[$e] = ['historias' => 0, 'puntos' => 0];
    }
    $resp = [];

    foreach ($historias as $h) {
        $pts = (int)$h['puntos'];
        $est = $h['estado'];
        $resumen['puntos'] += $pts;
        if ($est === 'finalizada') {
            $resumen['puntos_finalizados'] += $pts;
        }
        $estados[$est]['historias']++;
        $estados[$est]['puntos'] += $pts;

        $r = $h['responsable'];
        $resp[$r] ??= ['responsable' => $r, 'total' => 0, 'nuevas' => 0, 'activas' => 0,
                       'finalizadas' => 0, 'impedimentos' => 0, 'puntos' => 0, 'puntos_finalizados' => 0];
        $resp[$r]['total']++;
        $resp[$r]['puntos'] += $pts;
        $clave = ['nueva' => 'nuevas', 'activa' => 'activas', 'finalizada' => 'finalizadas', 'impedimento' => 'impedimentos'][$est];
        $resp[$r][$clave]++;
        if ($est === 'finalizada') {
            $resp[$r]['puntos_finalizados'] += $pts;
        }
    }
    $resumen['avance'] = porcentaje($resumen['puntos_finalizados'], $resumen['puntos']);
    ksort($resp);

    return ['resumen' => $resumen, 'por_estado' => $estados, 'por_responsable' => array_values($resp)];
}

// ---------- Enrutamiento ----------
$metodo = $_SERVER['REQUEST_METHOD'];
$ruta   = trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$p      = $ruta === '' ? [] : explode('/', $ruta);

if (($p[0] ?? '') !== 'informes' || $metodo !== 'GET') {
    json_out(($p[0] ?? '') === 'informes' ? 405 : 404, ['error' => 'Ruta no encontrada o método no permitido.']);
}

$seg = $p[1] ?? '';

if ($seg === 'health' && count($p) === 2) {
    json_out(200, ['servicio' => 'informes', 'estado' => 'ok']);
}

// GET /informes/general
if ($seg === 'general' && count($p) === 2) {
    $sprints   = consultar($cfg['sprints_url'] . '/sprints', 'sprints');
    $historias = consultar($cfg['historias_url'] . '/historias', 'historias');

    $lista = [];
    foreach ($sprints as $s) {
        $delSprint = array_values(array_filter($historias, fn($h) => (int)$h['sprint_id'] === (int)$s['id']));
        $r = armar($delSprint)['resumen'];
        $lista[] = $s + $r;
    }
    json_out(200, ['alcance' => 'general', 'sprint' => null, 'sprints' => $lista] + armar($historias));
}

// GET /informes/sprint/{id}
if ($seg === 'sprint' && count($p) === 3 && ctype_digit($p[2])) {
    $sprint    = consultar($cfg['sprints_url'] . '/sprints/' . $p[2], 'sprints', [404]);
    $historias = consultar($cfg['historias_url'] . '/historias?sprint_id=' . $p[2], 'historias');
    json_out(200, ['alcance' => 'sprint', 'sprint' => $sprint] + armar($historias));
}

json_out(404, ['error' => 'Ruta no encontrada.']);

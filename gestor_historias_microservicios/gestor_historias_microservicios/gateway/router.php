<?php
/**
 * API GATEWAY · puerto 8000
 * Punto de entrada único: sirve el front end y reenvía /api/* al servicio correspondiente.
 *   /api/sprints/...   → servicio de sprints   (8001)
 *   /api/historias/... → servicio de historias (8002)
 *   /api/informes/...  → servicio de informes  (8003)
 * Al estar el front y la API en el mismo origen no hace falta configurar CORS.
 */
const SERVICIOS = [
    '/api/sprints'   => 'http://127.0.0.1:8001/sprints',
    '/api/historias' => 'http://127.0.0.1:8002/historias',
    '/api/informes'  => 'http://127.0.0.1:8003/informes',
];

function responder_json(int $status, array $datos): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Reenvía la petición al servicio y devuelve su respuesta tal cual. */
function reenviar(string $url): void
{
    $cuerpo = file_get_contents('php://input');
    $opts = ['http' => [
        'method'        => $_SERVER['REQUEST_METHOD'],
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
        'timeout'       => 15,
    ]];
    if ($cuerpo !== '' && $cuerpo !== false) {
        $opts['http']['content'] = $cuerpo;
    }
    $respuesta = @file_get_contents($url, false, stream_context_create($opts));
    if ($respuesta === false) {
        responder_json(503, ['error' => 'El servicio solicitado no está disponible.']);
    }
    $codigo = 200;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $codigo = (int)$m[1];
    }
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo $respuesta;
    exit;
}

$ruta = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ---------- /api/* → servicios ----------
if (str_starts_with($ruta, '/api/')) {
    foreach (SERVICIOS as $prefijo => $destino) {
        if ($ruta === $prefijo || str_starts_with($ruta, $prefijo . '/')) {
            $url = $destino . substr($ruta, strlen($prefijo));
            if (!empty($_SERVER['QUERY_STRING'])) {
                $url .= '?' . $_SERVER['QUERY_STRING'];
            }
            reenviar($url);
        }
    }
    responder_json(404, ['error' => 'Ruta de API no encontrada.']);
}

// ---------- Archivos del front end ----------
$base    = realpath(__DIR__ . '/../front');
$archivo = realpath($base . ($ruta === '/' ? '/index.html' : $ruta));
if ($archivo === false || !str_starts_with($archivo, $base . DIRECTORY_SEPARATOR) || !is_file($archivo)) {
    http_response_code(404);
    exit('No encontrado');
}
$tipos = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8',
          'js' => 'application/javascript; charset=utf-8', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
header('Content-Type: ' . ($tipos[pathinfo($archivo, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
readfile($archivo);

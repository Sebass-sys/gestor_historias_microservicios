<?php
// Utilidades del servicio. (Se repite en cada servicio a propósito: son independientes.)

/** Responde en JSON y termina la petición. */
function json_out(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lee el cuerpo JSON de la petición. */
function json_in(): array
{
    $datos = json_decode((string)file_get_contents('php://input'), true);
    return is_array($datos) ? $datos : [];
}

/**
 * Llama a otro servicio por HTTP.
 * Devuelve [código HTTP, datos]; código 0 si el servicio no responde.
 */
function http_json(string $metodo, string $url, ?array $cuerpo = null): array
{
    $opts = ['http' => [
        'method'        => $metodo,
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
        'timeout'       => 5,
    ]];
    if ($cuerpo !== null) {
        $opts['http']['content'] = json_encode($cuerpo);
    }
    $respuesta = @file_get_contents($url, false, stream_context_create($opts));
    if ($respuesta === false) {
        return [0, null];
    }
    $codigo = 200;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $codigo = (int)$m[1];
    }
    return [$codigo, json_decode($respuesta, true)];
}

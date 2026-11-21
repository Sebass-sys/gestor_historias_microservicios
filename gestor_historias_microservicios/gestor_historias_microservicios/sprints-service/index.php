<?php
/**
 * SERVICIO DE SPRINTS · puerto 8001
 *   GET    /sprints          lista de sprints
 *   GET    /sprints/{id}     un sprint
 *   POST   /sprints          crear
 *   PUT    /sprints/{id}     actualizar
 *   DELETE /sprints/{id}     eliminar (solo si no tiene historias: se lo pregunta al servicio de historias)
 *   GET    /sprints/health   estado del servicio
 */
require __DIR__ . '/lib.php';
$cfg = require __DIR__ . '/config.php';

function fila(array $r): array
{
    $r['id'] = (int)$r['id'];
    return $r;
}

function buscar(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM sprints WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? fila($r) : null;
}

/** Valida y normaliza los datos de un sprint; responde 422 si hay errores. */
function validar(array $in): array
{
    $d = [
        'nombre'       => trim((string)($in['nombre'] ?? '')),
        'objetivo'     => trim((string)($in['objetivo'] ?? '')),
        'fecha_inicio' => (string)($in['fecha_inicio'] ?? ''),
        'fecha_fin'    => (string)($in['fecha_fin'] ?? ''),
    ];
    $e = [];
    if (mb_strlen($d['nombre']) < 3)    { $e['nombre'] = 'El nombre debe tener al menos 3 caracteres.'; }
    if (mb_strlen($d['objetivo']) > 255) { $e['objetivo'] = 'El objetivo no puede superar 255 caracteres.'; }

    $ini = DateTime::createFromFormat('Y-m-d', $d['fecha_inicio']);
    $fin = DateTime::createFromFormat('Y-m-d', $d['fecha_fin']);
    if (!$ini || $ini->format('Y-m-d') !== $d['fecha_inicio']) {
        $e['fecha_inicio'] = 'Fecha de inicio inválida.';
    }
    if (!$fin || $fin->format('Y-m-d') !== $d['fecha_fin']) {
        $e['fecha_fin'] = 'Fecha de fin inválida.';
    } elseif (!isset($e['fecha_inicio']) && $d['fecha_fin'] < $d['fecha_inicio']) {
        $e['fecha_fin'] = 'La fecha de fin no puede ser anterior al inicio.';
    }
    if ($e) {
        json_out(422, ['error' => 'Datos inválidos.', 'errores' => $e]);
    }
    return $d;
}

// ---------- Enrutamiento ----------
$metodo = $_SERVER['REQUEST_METHOD'];
$ruta   = trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$p      = $ruta === '' ? [] : explode('/', $ruta);

if (($p[0] ?? '') !== 'sprints') {
    json_out(404, ['error' => 'Ruta no encontrada.']);
}

try {
    // GET /sprints/health
    if (($p[1] ?? '') === 'health' && $metodo === 'GET') {
        json_out(200, ['servicio' => 'sprints', 'estado' => 'ok']);
    }

    // /sprints
    if (count($p) === 1) {
        if ($metodo === 'GET') {
            $filas = db()->query('SELECT * FROM sprints ORDER BY fecha_inicio DESC')->fetchAll();
            json_out(200, array_map('fila', $filas));
        }
        if ($metodo === 'POST') {
            $d = validar(json_in());
            $st = db()->prepare('INSERT INTO sprints (nombre, objetivo, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?)');
            $st->execute([$d['nombre'], $d['objetivo'], $d['fecha_inicio'], $d['fecha_fin']]);
            json_out(201, buscar((int)db()->lastInsertId()));
        }
        json_out(405, ['error' => 'Método no permitido.']);
    }

    // /sprints/{id}
    if (count($p) === 2 && ctype_digit($p[1])) {
        $id     = (int)$p[1];
        $sprint = buscar($id);
        if (!$sprint) {
            json_out(404, ['error' => 'El sprint no existe.']);
        }

        if ($metodo === 'GET') {
            json_out(200, $sprint);
        }
        if ($metodo === 'PUT') {
            $d = validar(json_in());
            $st = db()->prepare('UPDATE sprints SET nombre=?, objetivo=?, fecha_inicio=?, fecha_fin=? WHERE id=?');
            $st->execute([$d['nombre'], $d['objetivo'], $d['fecha_inicio'], $d['fecha_fin'], $id]);
            json_out(200, buscar($id));
        }
        if ($metodo === 'DELETE') {
            // Entre servicios no hay llaves foráneas: se le pregunta al servicio de historias.
            [$codigo, $historias] = http_json('GET', $cfg['historias_url'] . '/historias?sprint_id=' . $id);
            if ($codigo === 0) {
                json_out(503, ['error' => 'No se puede eliminar: el servicio de historias no responde.']);
            }
            if ($codigo !== 200 || !is_array($historias)) {
                json_out(502, ['error' => 'No se pudo verificar las historias del sprint.']);
            }
            if (count($historias) > 0) {
                json_out(409, ['error' => 'No se puede eliminar: el sprint tiene ' . count($historias)
                    . ' historia(s). Elimínalas o muévelas a otro sprint primero.']);
            }
            db()->prepare('DELETE FROM sprints WHERE id = ?')->execute([$id]);
            json_out(200, ['mensaje' => 'Sprint eliminado.']);
        }
        json_out(405, ['error' => 'Método no permitido.']);
    }

    json_out(404, ['error' => 'Ruta no encontrada.']);
} catch (PDOException $ex) {
    json_out(500, ['error' => 'Error de base de datos: verifica que importaste database.sql y sprints-service/config.php.']);
}

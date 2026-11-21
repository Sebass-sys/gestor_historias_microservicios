<?php
/**
 * SERVICIO DE HISTORIAS · puerto 8002
 *   GET    /historias?sprint_id=&estado=   lista (con filtros opcionales)
 *   GET    /historias/{id}                 una historia
 *   POST   /historias                      crear
 *   PUT    /historias/{id}                 actualizar
 *   POST   /historias/{id}/avanzar         pasar al siguiente estado
 *   DELETE /historias/{id}                 eliminar
 *   GET    /historias/health               estado del servicio
 * Al crear/actualizar consulta al servicio de sprints para comprobar que el sprint existe.
 */
require __DIR__ . '/lib.php';
$cfg = require __DIR__ . '/config.php';

const ESTADOS     = ['nueva', 'activa', 'finalizada', 'impedimento'];
const PRIORIDADES = ['alta', 'media', 'baja'];
const PUNTOS      = [1, 2, 3, 5, 8, 13, 21];                                           // serie de Fibonacci (Scrum)
const SIGUIENTE   = ['nueva' => 'activa', 'activa' => 'finalizada', 'impedimento' => 'activa'];

function fila(array $r): array
{
    foreach (['id', 'puntos', 'sprint_id'] as $campo) {
        $r[$campo] = (int)$r[$campo];
    }
    return $r;
}

function buscar(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM historias WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? fila($r) : null;
}

/** Regla: una historia finalizada guarda su fecha de cierre; las demás no tienen. */
function fecha_fin(string $estado, ?string $actual): ?string
{
    return $estado === 'finalizada' ? ($actual ?: date('Y-m-d')) : null;
}

/** Pregunta al servicio de sprints si el sprint existe. */
function sprint_existe(int $id): bool
{
    global $cfg;
    [$codigo] = http_json('GET', $cfg['sprints_url'] . '/sprints/' . $id);
    if ($codigo === 0) {
        json_out(503, ['error' => 'El servicio de sprints no responde: no se puede validar el sprint.']);
    }
    if ($codigo === 200) { return true; }
    if ($codigo === 404) { return false; }
    json_out(502, ['error' => 'Respuesta inesperada del servicio de sprints.']);
}

/** Valida y normaliza los datos de una historia; responde 422 si hay errores. */
function validar(array $in): array
{
    $d = [
        'titulo'         => trim((string)($in['titulo'] ?? '')),
        'descripcion'    => trim((string)($in['descripcion'] ?? '')),
        'responsable'    => trim((string)($in['responsable'] ?? '')),
        'prioridad'      => (string)($in['prioridad'] ?? 'media'),
        'estado'         => (string)($in['estado'] ?? 'nueva'),
        'puntos'         => (int)($in['puntos'] ?? 0),
        'fecha_creacion' => (string)($in['fecha_creacion'] ?? date('Y-m-d')),
        'sprint_id'      => (int)($in['sprint_id'] ?? 0),
    ];
    $e = [];
    if (mb_strlen($d['titulo']) < 5)       { $e['titulo'] = 'El título debe tener al menos 5 caracteres.'; }
    if (mb_strlen($d['descripcion']) < 10) { $e['descripcion'] = 'Describe la historia (mínimo 10 caracteres).'; }
    if (mb_strlen($d['responsable']) < 2)  { $e['responsable'] = 'Indica el responsable.'; }
    if (!in_array($d['prioridad'], PRIORIDADES, true)) { $e['prioridad'] = 'Selecciona una prioridad válida.'; }
    if (!in_array($d['estado'], ESTADOS, true))        { $e['estado'] = 'Selecciona un estado válido.'; }
    if (!in_array($d['puntos'], PUNTOS, true))         { $e['puntos'] = 'Selecciona los puntos de la historia.'; }

    $f = DateTime::createFromFormat('Y-m-d', $d['fecha_creacion']);
    if (!$f || $f->format('Y-m-d') !== $d['fecha_creacion']) {
        $e['fecha_creacion'] = 'Fecha inválida.';
    }
    if ($d['sprint_id'] <= 0) {
        $e['sprint_id'] = 'Selecciona un sprint.';
    } elseif (!sprint_existe($d['sprint_id'])) {
        $e['sprint_id'] = 'El sprint seleccionado no existe.';
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

if (($p[0] ?? '') !== 'historias') {
    json_out(404, ['error' => 'Ruta no encontrada.']);
}

try {
    // GET /historias/health
    if (($p[1] ?? '') === 'health' && $metodo === 'GET') {
        json_out(200, ['servicio' => 'historias', 'estado' => 'ok']);
    }

    // /historias
    if (count($p) === 1) {
        if ($metodo === 'GET') {
            $sql = 'SELECT * FROM historias WHERE 1 = 1';
            $par = [];
            if (isset($_GET['sprint_id']) && ctype_digit((string)$_GET['sprint_id'])) {
                $sql .= ' AND sprint_id = ?';
                $par[] = (int)$_GET['sprint_id'];
            }
            if (isset($_GET['estado']) && in_array($_GET['estado'], ESTADOS, true)) {
                $sql .= ' AND estado = ?';
                $par[] = $_GET['estado'];
            }
            $st = db()->prepare($sql . ' ORDER BY id DESC');
            $st->execute($par);
            json_out(200, array_map('fila', $st->fetchAll()));
        }
        if ($metodo === 'POST') {
            $d = validar(json_in());
            $st = db()->prepare(
                'INSERT INTO historias (titulo, descripcion, responsable, prioridad, estado, puntos, fecha_creacion, fecha_finalizacion, sprint_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$d['titulo'], $d['descripcion'], $d['responsable'], $d['prioridad'], $d['estado'],
                          $d['puntos'], $d['fecha_creacion'], fecha_fin($d['estado'], null), $d['sprint_id']]);
            json_out(201, buscar((int)db()->lastInsertId()));
        }
        json_out(405, ['error' => 'Método no permitido.']);
    }

    // /historias/{id} y /historias/{id}/avanzar
    if (ctype_digit($p[1] ?? '') && count($p) <= 3) {
        $id       = (int)$p[1];
        $historia = buscar($id);
        if (!$historia) {
            json_out(404, ['error' => 'La historia no existe.']);
        }

        if (count($p) === 3) {
            if ($p[2] === 'avanzar' && $metodo === 'POST') {
                if (!isset(SIGUIENTE[$historia['estado']])) {
                    json_out(409, ['error' => 'La historia ya está finalizada.']);
                }
                $nuevo = SIGUIENTE[$historia['estado']];
                db()->prepare('UPDATE historias SET estado = ?, fecha_finalizacion = ? WHERE id = ?')
                    ->execute([$nuevo, fecha_fin($nuevo, $historia['fecha_finalizacion']), $id]);
                json_out(200, buscar($id));
            }
            json_out(404, ['error' => 'Ruta no encontrada.']);
        }

        if ($metodo === 'GET') {
            json_out(200, $historia);
        }
        if ($metodo === 'PUT') {
            $d = validar(json_in());
            db()->prepare(
                'UPDATE historias SET titulo=?, descripcion=?, responsable=?, prioridad=?, estado=?, puntos=?,
                        fecha_creacion=?, fecha_finalizacion=?, sprint_id=? WHERE id=?'
            )->execute([$d['titulo'], $d['descripcion'], $d['responsable'], $d['prioridad'], $d['estado'], $d['puntos'],
                        $d['fecha_creacion'], fecha_fin($d['estado'], $historia['fecha_finalizacion']), $d['sprint_id'], $id]);
            json_out(200, buscar($id));
        }
        if ($metodo === 'DELETE') {
            db()->prepare('DELETE FROM historias WHERE id = ?')->execute([$id]);
            json_out(200, ['mensaje' => 'Historia eliminada.']);
        }
        json_out(405, ['error' => 'Método no permitido.']);
    }

    json_out(404, ['error' => 'Ruta no encontrada.']);
} catch (PDOException $ex) {
    json_out(500, ['error' => 'Error de base de datos: verifica que importaste database.sql y historias-service/config.php.']);
}

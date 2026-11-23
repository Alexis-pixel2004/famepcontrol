<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FasePiezaController;
use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\CatEstatusFase;
use App\Models\LogAuditoria;
use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$GLOBALS['ok']   = 0;
$GLOBALS['fail'] = 0;

function check($cond, $msg)
{
    if ($cond) {
        $GLOBALS['ok']++;
        echo "  [OK]   $msg\n";
    } else {
        $GLOBALS['fail']++;
        echo "  [FAIL] $msg\n";
    }
}

function xpost($data = [])
{
    return Request::create('/x', 'POST', $data);
}

function notificacionesDe(string $json): array
{
    return json_decode($json, true) ?: [];
}

function buscarNotificacion(array $notificaciones, string $clave, string $tipo): ?array
{
    foreach ($notificaciones as $notif) {
        if (($notif['clave_marca_pieza'] ?? null) === $clave && ($notif['tipo'] ?? null) === $tipo) {
            return $notif;
        }
    }

    return null;
}

echo "== RN-26: preparar actividad de produccion ==\n";

// Produccion (rol 1) genera los eventos que Calidad debe ver.
Auth::loginUsingId(1);

(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-NOT-001',
    'nombre_obra'      => 'Obra Notificaciones Test',
    'cliente'          => 'Cliente Notificaciones',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obra = Obra::where('codigo_obra', 'TEST-NOT-001')->firstOrFail();

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'NOT1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 100,
]), $obra->id);
$pieza = Pieza::where('clave_marca_pieza', 'NOT1')->where('obra_id', $obra->id)->firstOrFail();

$operario = operarioDePrueba('NOT');
$enProceso = CatEstatusFase::where('nombre', 'En Proceso')->value('id');

(new FasePiezaController)->store(xpost([
    'fase_id'         => 1,
    'operario_id'     => $operario->id,
    'estatus_fase_id' => $enProceso,
]), $pieza->id);

$fasePiezaId = DB::table('fases_pieza')->where('pieza_id', $pieza->id)->value('id');
check($fasePiezaId !== null, 'se registro una fase para la pieza NOT1');

// Los logs de auditoria deben existir antes de consultar el panel.
check(
    LogAuditoria::where('tabla_afectada', 'piezas')->where('accion', 'creado')
        ->where('registro_id', $pieza->id)->exists(),
    'log de pieza creada por Produccion presente'
);
check(
    LogAuditoria::where('tabla_afectada', 'fases_pieza')->where('accion', 'creado')
        ->where('registro_id', $fasePiezaId)->exists(),
    'log de fase creada por Produccion presente'
);

echo "== Calidad ve actividad del taller, no sus dictamenes ==\n";

// Calidad (rol 2) emite un dictamen sobre esa fase. No debe aparecer en el panel.
Auth::loginUsingId(2);
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'Dictamen de prueba para notificaciones',
    'resultado'   => 'Aprobado',
]), $fasePiezaId);

check(
    LogAuditoria::where('tabla_afectada', 'observaciones_calidad')->where('accion', 'creado')->exists(),
    'el dictamen queda auditado (pero no debe notificarse)'
);

$respuesta = (new DashboardController)->getNotificacionesApi();
check($respuesta->getStatusCode() === 200, 'API responde 200 para Calidad');
$notificaciones = notificacionesDe($respuesta->getContent());

check(count($notificaciones) > 0, 'Calidad recibe actividad del taller');

$faseNotif = buscarNotificacion($notificaciones, 'NOT1', 'fase_agregada');
check($faseNotif !== null, 'aparece el evento de fase agregada');
check(($faseNotif['titulo'] ?? '') === 'Nueva fase registrada', 'titulo de fase agregada');
check(str_contains($faseNotif['detalle'] ?? '', 'Fase'), 'detalle de fase agregada legible');
check(($faseNotif['obra_nombre'] ?? '') === 'Obra Notificaciones Test', 'fase incluye la obra');
check(! empty($faseNotif['actor_nombre']), 'fase incluye quien la registro');
check(
    (bool) preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} (AM|PM)$/', $faseNotif['fecha_registro'] ?? ''),
    'fecha de la fase en formato local'
);

$piezaNotif = buscarNotificacion($notificaciones, 'NOT1', 'pieza_nueva');
check($piezaNotif !== null, 'aparece el evento de pieza nueva');
check(($piezaNotif['titulo'] ?? '') === 'Nueva marca registrada', 'titulo de pieza nueva');
check(($piezaNotif['obra_nombre'] ?? '') === 'Obra Notificaciones Test', 'pieza incluye la obra');
check(! empty($piezaNotif['url_pieza']), 'pieza activa incluye enlace a la marca');

$ids = array_column($notificaciones, 'id');
check(
    count($ids) === count(array_unique($ids)) && ! in_array(0, $ids, true),
    'los ids son unicos y crecientes (logs_auditoria)'
);
check(min($ids) > 0, 'ids positivos');

$sinDictamenes = true;
foreach ($notificaciones as $notif) {
    if (array_key_exists('resultado', $notif) || array_key_exists('descripcion', $notif)
        || array_key_exists('inspector_nombre', $notif)) {
        $sinDictamenes = false;
    }
}
check($sinDictamenes, 'ningun dictamen aparece en el panel');

echo "== La propia Calidad no ve sus acciones ==\n";

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'NOTOWN',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 50,
]), $obra->id);

check(
    LogAuditoria::where('tabla_afectada', 'piezas')->where('accion', 'creado')
        ->where('registro_id', Pieza::where('clave_marca_pieza', 'NOTOWN')->value('id'))
        ->where('usuario_id', 2)->exists(),
    'Calidad creo una pieza (log presente)'
);

$notificaciones = notificacionesDe((new DashboardController)->getNotificacionesApi()->getContent());
check(buscarNotificacion($notificaciones, 'NOTOWN', 'pieza_nueva') === null, 'su propia pieza NO se notifica');

echo "== Produccion conserva su panel de dictamenes ==\n";

Auth::loginUsingId(1);
$respuestaProduccion = (new DashboardController)->getNotificacionesApi();
check($respuestaProduccion->getStatusCode() === 200, 'API responde 200 para Produccion');
$dictamenes = notificacionesDe($respuestaProduccion->getContent());
check(count($dictamenes) > 0, 'Produccion recibe los dictamenes de Calidad');

$dictamenNot1 = null;
foreach ($dictamenes as $dictamen) {
    if (($dictamen['clave_marca_pieza'] ?? null) === 'NOT1') {
        $dictamenNot1 = $dictamen;
        break;
    }
}
check($dictamenNot1 !== null, 'el dictamen de la marca NOT1 llega a Produccion');
check(($dictamenNot1['resultado'] ?? '') === 'Aprobado', 'dictamen incluye el resultado');
check(str_contains($dictamenNot1['descripcion'] ?? '', 'Dictamen de prueba'), 'dictamen incluye la descripcion');
check(! empty($dictamenNot1['inspector_nombre']), 'dictamen incluye el inspector');
check(($dictamenNot1['obra_nombre'] ?? '') === 'Obra Notificaciones Test', 'dictamen incluye la obra');
check(($dictamenNot1['pieza_id'] ?? null) === $pieza->id, 'dictamen apunta a la pieza correcta');
check(! empty($dictamenNot1['url_pieza']), 'dictamen incluye enlace a la marca');
check(
    ! array_key_exists('tipo', $dictamenNot1)
        && ! array_key_exists('estado', $dictamenNot1)
        && ! array_key_exists('titulo', $dictamenNot1),
    'el payload de Produccion no se mezcla con el de Calidad'
);

echo "== Pieza eliminada: aviso conservado sin link roto ==\n";

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'NOTDEL',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 25,
]), $obra->id);
$piezaEliminada = Pieza::where('clave_marca_pieza', 'NOTDEL')->where('obra_id', $obra->id)->firstOrFail();
$logCreacionId = LogAuditoria::where('tabla_afectada', 'piezas')->where('accion', 'creado')
    ->where('registro_id', $piezaEliminada->id)->value('id');
check($logCreacionId !== null, 'log de creacion de NOTDEL presente');

(new PiezaController)->destroy($piezaEliminada->id);
check(Pieza::find($piezaEliminada->id) === null, 'la pieza NOTDEL fue eliminada');
check(
    LogAuditoria::where('tabla_afectada', 'piezas')->where('accion', 'eliminado')
        ->where('registro_id', $piezaEliminada->id)->exists(),
    'el log de eliminacion no borra el historial'
);

Auth::loginUsingId(2);
$notificaciones = notificacionesDe((new DashboardController)->getNotificacionesApi()->getContent());
$eliminada = null;
foreach ($notificaciones as $notif) {
    if (($notif['id'] ?? null) === $logCreacionId) {
        $eliminada = $notif;
        break;
    }
}

check($eliminada !== null, 'la notificacion de la pieza eliminada sigue visible');
check(($eliminada['estado'] ?? '') === 'registro_eliminado', 'marcada como registro_eliminado');
check(($eliminada['clave_marca_pieza'] ?? '') === 'NOTDEL', 'marca reconstruida desde datos_nuevos');
check(($eliminada['obra_nombre'] ?? '') === 'Obra Notificaciones Test', 'obra reconstruida desde datos_nuevos');
check(
    array_key_exists('url_pieza', $eliminada) && $eliminada['url_pieza'] === null,
    'sin enlace roto hacia la pieza eliminada'
);

echo "== Campana distinta por rol ==\n";

$htmlCalidad = (new DashboardController)->index()->render();
check(str_contains($htmlCalidad, 'Actividad del Taller'), 'Calidad ve el titulo del panel');
check(str_contains($htmlCalidad, 'aria-label="Actividad del Taller"'), 'Calidad ve la campana de actividad');
check(str_contains($htmlCalidad, 'famep_ultimo_id_visto_2'), 'badge por usuario (Calidad)');
check(! str_contains($htmlCalidad, 'Notificaciones de Calidad'), 'Calidad NO ve el panel de dictamenes');

Auth::loginUsingId(1);
$htmlProduccion = (new DashboardController)->index()->render();
check(str_contains($htmlProduccion, 'Notificaciones de Calidad'), 'Produccion conserva su titulo historico');
check(str_contains($htmlProduccion, 'aria-label="Notificaciones"'), 'Produccion ve su campana original');
check(str_contains($htmlProduccion, 'famep_ultimo_id_visto_1'), 'badge por usuario (Produccion)');
check(! str_contains($htmlProduccion, 'Actividad del Taller'), 'Produccion NO ve el panel de Calidad');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";
if ($GLOBALS['fail'] > 0) {
    exit(1);
}

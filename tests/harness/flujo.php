<?php

use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\FasePiezaController;
use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\CatEstatusFase;
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

function errorDe($resp)
{
    return (string) ($resp->getSession()->get('error') ?? '');
}

echo "== RF-02/RNF-02: guards por rol ==\n";

// Datos de prueba
(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-FL-001',
    'nombre_obra'      => 'Obra Prueba Flujo',
    'cliente'          => 'Cliente Test',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obra = Obra::where('codigo_obra', 'TEST-FL-001')->firstOrFail();

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'TF1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 150,
    'cantidad'           => 1,
]), $obra->id);

$pieza = Pieza::where('clave_marca_pieza', 'TF1')->firstOrFail();

$enProceso  = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$completada = CatEstatusFase::where('nombre', 'Completada')->value('id');

// Operarios propios de la suite: la base de desarrollo tiene operarios reales
// con fases activas, así que las pruebas no deben reutilizarlos.
$operarioA = operarioDePrueba('FL-A');
$operarioB = operarioDePrueba('FL-B');

$fasePiezaId = fn ($faseId) => DB::table('fases_pieza')
    ->where('pieza_id', $pieza->id)
    ->where('fase_id', $faseId)
    ->value('id');

// Calidad intenta registrar avance => bloqueado
Auth::loginUsingId(2);
$r = (new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $enProceso,
]), $pieza->id);
check(str_contains(errorDe($r), 'Solo el Jefe de Producción'), 'calidad no puede registrar avance');

// Produccion intenta dictaminar => bloqueado
Auth::loginUsingId(1);
$r = (new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'x', 'resultado' => 'Aprobado',
]), $fasePiezaId(1) ?: 1);
check(str_contains(errorDe($r), 'Solo el Jefe de Calidad'), 'produccion no puede dictaminar');

// Produccion intenta subir evidencia => bloqueado
$r = (new EvidenciaController)->store(xpost(), 1);
check(str_contains(errorDe($r), 'Solo el Jefe de Calidad puede adjuntar evidencias'), 'produccion no puede adjuntar evidencias');

echo "== RF-06: no repetir operario en fase En Proceso ==\n";

Auth::loginUsingId(1);
(new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $enProceso,
]), $pieza->id);

$r = (new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $enProceso,
]), $pieza->id);
check(str_contains(errorDe($r), 'ya está asignado'), 'no repite el mismo operario en fase En Proceso');

echo "== RF-05: fechas automaticas ==\n";

$fila = DB::table('fases_pieza')->where('id', $fasePiezaId(1))->first();
check(
    $fila->fecha_inicio !== null && $fila->fecha_dictamen === null && $fila->fecha_termino === null,
    'fase iniciada: fecha_inicio registrada, dictamen y cierre nulos'
);

echo "== RF-08: no se cierra sin dictamen Aprobado ==\n";

Auth::loginUsingId(1);
$r = (new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $completada,
]), $pieza->id);
check(str_contains(errorDe($r), 'Calidad aún no ha emitido'), 'cerrar sin Aprobado => bloqueado');

echo "== Dictamen Aprobado, cierre y bloqueo posterior ==\n";

Auth::loginUsingId(2);
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'aprobado', 'resultado' => 'Aprobado',
]), $fasePiezaId(1));

$fila = DB::table('fases_pieza')->where('id', $fasePiezaId(1))->first();
check($fila->fecha_dictamen !== null, 'fecha_dictamen registrada al aprobar');

Auth::loginUsingId(1);
(new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $completada,
]), $pieza->id);

$fila = DB::table('fases_pieza')->where('id', $fasePiezaId(1))->first();
check((int) $fila->estatus_fase_id === (int) $completada && $fila->fecha_termino !== null, 'cierre: fase Completada y fecha_termino registrada');

Auth::loginUsingId(2);
$r = (new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'otro dictamen', 'resultado' => 'Rechazado',
]), $fasePiezaId(1));
check(str_contains(errorDe($r), 'nuevos dictamenes'), 'fase terminada no admite nuevos dictamenes');

echo "== RF-08: reproceso (Enviado a Reproceso) ==\n";

Auth::loginUsingId(1);
(new FasePiezaController)->store(xpost([
    'fase_id' => 2, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $enProceso,
]), $pieza->id);

Auth::loginUsingId(2);
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'observado', 'resultado' => 'Enviado a Reproceso',
]), $fasePiezaId(2));

$pieza->refresh();
$f2 = DB::table('fases_pieza')->where('id', $fasePiezaId(2))->first();
$nombreFase2 = DB::table('cat_estatus_fase')->where('id', $f2->estatus_fase_id)->value('nombre');
check($nombreFase2 === 'En Reproceso', 'Enviado a Reproceso => fase En Reproceso');
check($pieza->estatus?->nombre === 'En Revisión', 'pieza queda En Revisión');

echo "== RF-06: retomar reproceso con el mismo operario es valido ==\n";

Auth::loginUsingId(1);
$r = (new FasePiezaController)->store(xpost([
    'fase_id' => 2, 'operario_id' => $operarioA->id, 'estatus_fase_id' => $enProceso,
]), $pieza->id);

$f2 = DB::table('fases_pieza')->where('id', $fasePiezaId(2))->first();
check(
    $r->getSession()->get('success') === 'Avance de fase registrado correctamente.'
    && (int) $f2->estatus_fase_id === (int) $enProceso,
    'retomar reproceso con el mismo operario permitido'
);

echo "== RF-08: rechazo detiene la marca ==\n";

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'TF2',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 50,
    'cantidad'           => 1,
]), $obra->id);

$pieza2 = Pieza::where('clave_marca_pieza', 'TF2')->firstOrFail();
$fasePiezaId2 = fn ($faseId) => DB::table('fases_pieza')
    ->where('pieza_id', $pieza2->id)
    ->where('fase_id', $faseId)
    ->value('id');

Auth::loginUsingId(1);
(new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioB->id, 'estatus_fase_id' => $enProceso,
]), $pieza2->id);

Auth::loginUsingId(2);
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'rechazado', 'resultado' => 'Rechazado',
]), $fasePiezaId2(1));

$pieza2->refresh();
check($pieza2->estatus?->nombre === 'Detenida', 'Rechazado => pieza Detenida');

Auth::loginUsingId(1);
$r = (new FasePiezaController)->store(xpost([
    'fase_id' => 1, 'operario_id' => $operarioB->id, 'estatus_fase_id' => $enProceso,
]), $pieza2->id);
check(str_contains(errorDe($r), 'no se puede modificar'), 'fase Rechazada no se puede retomar');

Auth::loginUsingId(2);
$r = (new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'intento', 'resultado' => 'Aprobado',
]), $fasePiezaId2(1));
check(str_contains(errorDe($r), 'No se puede dictaminar'), 'fase Rechazada no admite dictamen');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}
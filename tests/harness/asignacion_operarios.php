<?php

use App\Http\Controllers\FasePiezaController;
use App\Models\CatEstatusFase;
use App\Models\CatFase;
use App\Models\FasePieza;
use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    return (string) ($resp->getSession()?->get('error') ?? '');
}

function successDe($resp)
{
    return (string) ($resp->getSession()?->get('success') ?? '');
}

// El arnés no avanza el ciclo de vida de la sesión entre peticiones, así que
// los mensajes flash de un redirect anterior siguen visibles. Se limpian
// antes de las verificaciones que esperan una respuesta exitosa.
function limpiarSesion()
{
    session()->forget(['error', 'success']);
}

$produccion = \App\Models\User::where('rol_id', 1)->firstOrFail();

$enProceso    = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$enReproceso  = CatEstatusFase::where('nombre', 'En Reproceso')->value('id');
$completada   = CatEstatusFase::where('nombre', 'Completada')->value('id');
$aprobada     = CatEstatusFase::where('nombre', 'Aprobada por Calidad')->value('id');
$rechazada    = CatEstatusFase::where('nombre', 'Rechazada')->value('id');

$armado    = CatFase::where('nombre', 'Armado')->value('id');
$soldadura = CatFase::where('nombre', 'Soldadura')->value('id');
$pintura   = CatFase::where('nombre', 'Pintura')->value('id');

// Datos base: dos obras y cuatro piezas para poder combinar fases sin chocar
// con el índice único (pieza_id, fase_id).
$obraA = Obra::create([
    'codigo_obra'      => 'TEST-AS-001',
    'nombre_obra'      => 'Obra Asignacion A',
    'cliente'          => 'Cliente Asignacion',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]);
$obraB = Obra::create([
    'codigo_obra'      => 'TEST-AS-002',
    'nombre_obra'      => 'Obra Asignacion B',
    'cliente'          => 'Cliente Asignacion',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]);

function crearPieza($obraId, $marca)
{
    return Pieza::create([
        'obra_id'            => $obraId,
        'tipo_estructura_id' => 1,
        'estatus_id'         => \App\Models\CatEstatusPieza::where('nombre', 'Pendiente')->value('id') ?? 1,
        'clave_marca_pieza'  => $marca,
        'peso_kg'            => 100,
        'cantidad'           => 1,
    ]);
}

$pa1 = crearPieza($obraA->id, 'PA1');
$pa2 = crearPieza($obraA->id, 'PA2');
$pb1 = crearPieza($obraB->id, 'PB1');
$pb2 = crearPieza($obraB->id, 'PB2');
$pb3 = crearPieza($obraB->id, 'PB3');
$pc1 = crearPieza($obraA->id, 'PC1');

$opLibre      = operarioDePrueba('AS-LIBRE');
$opBloqueo    = operarioDePrueba('AS-BLOQ');
$opReproceso  = operarioDePrueba('AS-REPRO');
$opCompletada = operarioDePrueba('AS-COMP');
$opRechazada  = operarioDePrueba('AS-RECH');
$opCierre     = operarioDePrueba('AS-CIERRE');
$opReemplazo  = operarioDePrueba('AS-REEMP');
$opSetup      = operarioDePrueba('AS-SET');

Auth::loginUsingId($produccion->id);

echo "== 1. Operario libre puede tomar una fase nueva ==\n";

limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opLibre->id,
    'estatus_fase_id' => $enProceso,
]), $pa1->id);

check(errorDe($r) === '', 'operario libre: sin error');
check(successDe($r) !== '', 'operario libre: mensaje de exito');
check($opLibre->faseActiva()?->pieza_id === $pa1->id, 'operario libre queda ocupado en PA1/Armado');

echo "== 2. Operario ocupado no puede tomar otra pieza ==\n";

(new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opBloqueo->id,
    'estatus_fase_id' => $enProceso,
]), $pa1->id);

$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opBloqueo->id,
    'estatus_fase_id' => $enProceso,
]), $pb1->id);

$error = errorDe($r);
check(str_contains($error, 'ya está asignado'), 'otra pieza: bloqueado');
check(str_contains($error, 'Armado'), 'el mensaje nombra la fase ocupada');
check(str_contains($error, 'PA1'), 'el mensaje nombra la marca ocupada');
check(str_contains($error, 'TEST-AS-001'), 'el mensaje nombra el codigo de obra');
check(str_contains($error, 'En Proceso'), 'el mensaje nombra el estado de la fase');
check(! FasePieza::where('pieza_id', $pb1->id)->where('fase_id', $armado)->exists(), 'no se creo la fase bloqueada');

echo "== 3. Operario ocupado no puede tomar otra fase de la misma pieza ==\n";

// El nuevo orden exige Armado y Soldadura completadas antes de Pintura.
// Preparamos pa1 con ese historial y dejamos a opBloqueo activo en otra pieza
// para que la prueba siga verificando la regla de "un operario, una tarea".
FasePieza::where('pieza_id', $pa1->id)->where('fase_id', $armado)
    ->update(['estatus_fase_id' => $completada, 'fecha_termino' => now()]);

FasePieza::create([
    'pieza_id'           => $pa1->id,
    'fase_id'            => $soldadura,
    'operario_id'        => $opSetup->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $completada,
    'fecha_inicio'       => now()->subDay(),
    'fecha_termino'      => now(),
]);

FasePieza::create([
    'pieza_id'           => $pb2->id,
    'fase_id'            => $armado,
    'operario_id'        => $opBloqueo->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $enProceso,
    'fecha_inicio'       => now(),
]);

$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $pintura,
    'operario_id'     => $opBloqueo->id,
    'estatus_fase_id' => $enProceso,
]), $pa1->id);

check(str_contains(errorDe($r), 'ya está asignado'), 'misma pieza, otra fase: bloqueado');
check(! FasePieza::where('pieza_id', $pa1->id)->where('fase_id', $pintura)->exists(), 'no se creo la segunda fase');

echo "== 4. Retomar un reproceso con el mismo operario sigue permitido ==\n";

(new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opReproceso->id,
    'estatus_fase_id' => $enProceso,
]), $pb1->id);

FasePieza::where('pieza_id', $pb1->id)
    ->where('fase_id', $armado)
    ->update(['estatus_fase_id' => $enReproceso]);

limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opReproceso->id,
    'estatus_fase_id' => $enProceso,
]), $pb1->id);

check(errorDe($r) === '', 'reproceso misma fase: no lo bloquea la regla 2d');
check(
    FasePieza::where('pieza_id', $pb1->id)->where('fase_id', $armado)->value('estatus_fase_id') === $enProceso,
    'reproceso misma fase: vuelve a En Proceso'
);

echo "== 5. Una fase Completada no ocupa al operario ==\n";

// pb1 necesita Armado completada para poder registrar Soldadura.
FasePieza::where('pieza_id', $pb1->id)->where('fase_id', $armado)
    ->update(['estatus_fase_id' => $completada, 'fecha_termino' => now()]);

FasePieza::create([
    'pieza_id'           => $pa2->id,
    'fase_id'            => $soldadura,
    'operario_id'        => $opCompletada->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $completada,
    'fecha_inicio'       => now(),
    'fecha_termino'      => now(),
]);

limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $soldadura,
    'operario_id'     => $opCompletada->id,
    'estatus_fase_id' => $enProceso,
]), $pb1->id);

check(errorDe($r) === '', 'Completada: el operario queda libre');
check($opCompletada->faseActiva()?->pieza_id === $pb1->id, 'Completada: puede iniciar otra fase');

echo "== 6. Una fase Rechazada no ocupa al operario ==\n";

// pb1 ya tiene Soldadura (registrada en el paso 5); se cierra para habilitar Pintura.
FasePieza::where('pieza_id', $pb1->id)->where('fase_id', $soldadura)
    ->update(['estatus_fase_id' => $completada, 'fecha_termino' => now()]);

FasePieza::create([
    'pieza_id'           => $pa2->id,
    'fase_id'            => $pintura,
    'operario_id'        => $opRechazada->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $rechazada,
    'fecha_inicio'       => now(),
]);

limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $pintura,
    'operario_id'     => $opRechazada->id,
    'estatus_fase_id' => $enProceso,
]), $pb1->id);

check(errorDe($r) === '', 'Rechazada: el operario queda libre');
check($opRechazada->faseActiva()?->pieza_id === $pb1->id, 'Rechazada: puede iniciar otra fase');

echo "== 7. Cerrar una fase no se bloquea por estar ocupado ==\n";

FasePieza::create([
    'pieza_id'           => $pa1->id,
    'fase_id'            => $pintura,
    'operario_id'        => $opCierre->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $aprobada,
    'fecha_inicio'       => now(),
]);

// Segunda tarea activa: sin la excepcion de cierre, la regla 2d la bloquearia.
// pb2 ya tiene Armado ocupado por opBloqueo (paso 3), asi que usamos pb3.
FasePieza::create([
    'pieza_id'           => $pb3->id,
    'fase_id'            => $armado,
    'operario_id'        => $opCierre->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $enProceso,
    'fecha_inicio'       => now(),
]);

limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $pintura,
    'operario_id'     => $opCierre->id,
    'estatus_fase_id' => $completada,
]), $pa1->id);

check(errorDe($r) === '', 'cierre: no se bloquea');
check(
    FasePieza::where('pieza_id', $pa1->id)->where('fase_id', $pintura)->value('estatus_fase_id') === $completada,
    'cierre: la fase queda Completada'
);

echo "== 8. Otro operario si puede tomar la fase de un operario ocupado ==\n";

// pa1/Armado ya quedo Completada en el paso 3. Usamos pc1 para mantener
// una fase activa real de opLibre y poder probar el relevo.
limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opLibre->id,
    'estatus_fase_id' => $enProceso,
]), $pc1->id);
check(errorDe($r) === '', 'relevo: setup con el operario original');
check($opLibre->faseActiva()?->pieza_id === $pc1->id, 'relevo: operario original ocupado en PC1');

limpiarSesion();
$r = (new FasePiezaController)->store(xpost([
    'fase_id'         => $armado,
    'operario_id'     => $opReemplazo->id,
    'estatus_fase_id' => $enProceso,
]), $pc1->id);

check(errorDe($r) === '', 'relevo: el nuevo operario no esta ocupado');
check(
    FasePieza::where('pieza_id', $pc1->id)->where('fase_id', $armado)->value('operario_id') === $opReemplazo->id,
    'relevo: la fase queda con el nuevo operario'
);
check($opLibre->faseActiva() === null, 'relevo: el operario anterior queda libre');

echo "== 9. Un operario inactivo no puede recibir una fase ==\n";

$opInactivo = operarioDePrueba('AS-INAC');
$opInactivo->update(['activo' => false]);

$rechazado = null;

try {
    (new FasePiezaController)->store(xpost([
        'fase_id'         => $pintura,
        'operario_id'     => $opInactivo->id,
        'estatus_fase_id' => $enProceso,
    ]), $pb2->id);
} catch (\Illuminate\Validation\ValidationException $e) {
    $rechazado = $e;
}

check($rechazado !== null, 'operario inactivo: la validacion falla');
check(
    $rechazado !== null && array_key_exists('operario_id', $rechazado->errors()),
    'operario inactivo: el error apunta a operario_id'
);
check(! FasePieza::where('pieza_id', $pb2->id)->where('fase_id', $pintura)->exists(), 'operario inactivo: no se creo la fase');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    fwrite(STDERR, "\n[ERROR] {$GLOBALS['fail']} verificaciones fallaron.\n");
    exit(1);
}

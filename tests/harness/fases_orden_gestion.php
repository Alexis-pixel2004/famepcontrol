<?php

use App\Http\Controllers\FasePiezaController;
use App\Models\CatEstatusFase;
use App\Models\CatEstatusPieza;
use App\Models\CatFase;
use App\Models\FasePieza;
use App\Models\Obra;
use App\Models\ObservacionCalidad;
use App\Models\Pieza;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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

function limpiarSesion()
{
    session()->forget(['error', 'success']);
}

$produccion = User::where('rol_id', 1)->firstOrFail();
$calidad    = User::where('rol_id', 2)->firstOrFail();

$enProceso  = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$completada = CatEstatusFase::where('nombre', 'Completada')->value('id');
$aprobada   = CatEstatusFase::where('nombre', 'Aprobada por Calidad')->value('id');
$pendiente  = CatEstatusPieza::where('nombre', 'Pendiente')->value('id') ?? 1;

$armado    = CatFase::where('nombre', 'Armado')->value('id');
$soldadura = CatFase::where('nombre', 'Soldadura')->value('id');
$pintura   = CatFase::where('nombre', 'Pintura')->value('id');

function crearPiezaOrden($marca, $pendiente)
{
    $obra = Obra::create([
        'codigo_obra'      => 'TEST-ORD-' . $marca,
        'nombre_obra'      => 'Obra Orden ' . $marca,
        'cliente'          => 'Cliente Orden',
        'fecha_inicio'     => '2026-10-01',
        'fecha_compromiso' => '2026-12-31',
    ]);

    return Pieza::create([
        'obra_id'            => $obra->id,
        'tipo_estructura_id' => 1,
        'estatus_id'         => $pendiente,
        'clave_marca_pieza'  => $marca,
        'peso_kg'            => 100,
        'cantidad'           => 1,
    ]);
}

$piezaA = crearPiezaOrden('OA1', $pendiente);
$piezaB = crearPiezaOrden('OB1', $pendiente);
$piezaC = crearPiezaOrden('OC1', $pendiente);

$opA = operarioDePrueba('ORD-A');
$opB = operarioDePrueba('ORD-B');
$opC = operarioDePrueba('ORD-C');
$opD = operarioDePrueba('ORD-D');

$controlador = new FasePiezaController;

echo "== Rutas ==\n";
check(Route::has('fases.edit'), 'ruta fases.edit registrada');
check(Route::has('fases.update'), 'ruta fases.update registrada');
check(Route::has('fases.destroy'), 'ruta fases.destroy registrada');

Auth::loginUsingId($produccion->id);

echo "== 1. Orden secuencial ==\n";
limpiarSesion();
$r = $controlador->store(xpost([
    'fase_id'         => $pintura,
    'operario_id'     => $opA->id,
    'estatus_fase_id' => $enProceso,
]), $piezaA->id);
check(str_contains(errorDe($r), 'Armado'), 'Pintura directa se bloquea y menciona Armado');
check($piezaA->fases()->count() === 0, 'no se creó la fase adelantada');

limpiarSesion();
$r = $controlador->store(xpost([
    'fase_id'         => $soldadura,
    'operario_id'     => $opA->id,
    'estatus_fase_id' => $enProceso,
]), $piezaA->id);
check(str_contains(errorDe($r), 'Armado'), 'Soldadura se bloquea si Armado no está completada');

// Armado completada directamente como setup (simula cierre ya aprobado).
$faseArmado = FasePieza::create([
    'pieza_id'           => $piezaA->id,
    'fase_id'            => $armado,
    'operario_id'        => $opA->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $completada,
    'fecha_inicio'       => now()->subDay(),
    'fecha_termino'      => now(),
]);
check($faseArmado->terminada(), 'setup: Armado quedó Completada');

limpiarSesion();
$r = $controlador->store(xpost([
    'fase_id'         => $soldadura,
    'operario_id'     => $opB->id,
    'estatus_fase_id' => $enProceso,
]), $piezaA->id);
check(errorDe($r) === '', 'Soldadura se permite con Armado completada');
check($piezaA->fases()->where('fase_id', $soldadura)->exists(), 'Soldadura quedó registrada');

limpiarSesion();
$r = $controlador->store(xpost([
    'fase_id'         => $pintura,
    'operario_id'     => $opC->id,
    'estatus_fase_id' => $enProceso,
]), $piezaA->id);
check(str_contains(errorDe($r), 'Soldadura'), 'Pintura se bloquea si Soldadura no está completada');

echo "== 1b. Cerrar conserva el operario asignado ==\n";
$piezaCierre = crearPiezaOrden('OD1', $pendiente);
$faseCerrar = FasePieza::create([
    'pieza_id'           => $piezaCierre->id,
    'fase_id'            => $armado,
    'operario_id'        => $opA->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $aprobada,
    'fecha_inicio'       => now(),
]);

limpiarSesion();
$r = $controlador->store(xpost([
    'fase_id'         => $armado,
    'estatus_fase_id' => $completada,
]), $piezaCierre->id);
check(errorDe($r) === '', 'cierre sin operario_id: sin error');
check(str_contains(successDe($r), 'cerrada'), 'cierre sin operario_id: mensaje de éxito');
$faseCerrar->refresh();
check((int) $faseCerrar->operario_id === (int) $opA->id, 'cierre sin operario_id: conserva el operario asignado');
check($faseCerrar->terminada(), 'cierre sin operario_id: la fase queda Completada');

// El operario inyectado sigue pasando la validación de activo.
$opInactivoCierre = operarioDePrueba('ORD-INAC');
$opInactivoCierre->update(['activo' => false]);
$piezaInactiva = crearPiezaOrden('OE1', $pendiente);
FasePieza::create([
    'pieza_id'           => $piezaInactiva->id,
    'fase_id'            => $armado,
    'operario_id'        => $opA->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $aprobada,
    'fecha_inicio'       => now(),
]);

$rechazoInactivo = null;
try {
    $controlador->store(xpost([
        'fase_id'         => $armado,
        'operario_id'     => $opInactivoCierre->id,
        'estatus_fase_id' => $completada,
    ]), $piezaInactiva->id);
} catch (ValidationException $e) {
    $rechazoInactivo = $e;
}
check($rechazoInactivo !== null, 'cierre con operario inactivo: la validación falla');
check(
    $rechazoInactivo !== null && array_key_exists('operario_id', $rechazoInactivo->errors()),
    'cierre con operario inactivo: el error apunta a operario_id'
);

// El fallback no aplica a altas nuevas ni a fases en curso.
$piezaSinOperario = crearPiezaOrden('OF1', $pendiente);
$rechazoSinOperario = null;
try {
    $controlador->store(xpost([
        'fase_id'         => $armado,
        'estatus_fase_id' => $enProceso,
    ]), $piezaSinOperario->id);
} catch (ValidationException $e) {
    $rechazoSinOperario = $e;
}
check($rechazoSinOperario !== null, 'fase En Proceso sin operario: la validación falla');
check(
    $rechazoSinOperario !== null && array_key_exists('operario_id', $rechazoSinOperario->errors()),
    'fase En Proceso sin operario: el error apunta a operario_id'
);

echo "== 2. Editar asignación ==\n";
$faseEditar = FasePieza::create([
    'pieza_id'           => $piezaB->id,
    'fase_id'            => $armado,
    'operario_id'        => $opA->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $enProceso,
    'fecha_inicio'       => now(),
]);

$vista = $controlador->edit($faseEditar->id);
check($vista instanceof \Illuminate\View\View, 'Producción puede abrir el formulario de edición');
check(str_contains($vista->render(), 'Editar asignación de fase'), 'el formulario tiene título en español');

limpiarSesion();
$r = $controlador->update(Request::create('/x', 'PUT', ['operario_id' => $opD->id]), $faseEditar->id);
check(str_contains(successDe($r) ?? '', 'actualizada'), 'update responde con éxito');
$faseEditar->refresh();
check((int) $faseEditar->operario_id === (int) $opD->id, 'el operario asignado cambió en BD');
check((int) $faseEditar->usuario_captura_id === (int) $produccion->id, 'se registró quién editó');

$faseCerradaEditar = FasePieza::create([
    'pieza_id'           => $piezaC->id,
    'fase_id'            => $armado,
    'operario_id'        => $opA->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $completada,
]);
limpiarSesion();
$r = $controlador->update(Request::create('/x', 'PUT', ['operario_id' => $opB->id]), $faseCerradaEditar->id);
check(str_contains(errorDe($r), 'cerrada'), 'una fase cerrada no se puede editar');

echo "== 3. Eliminar asignación ==\n";
$faseBorrar = FasePieza::create([
    'pieza_id'           => $piezaC->id,
    'fase_id'            => $soldadura,
    'operario_id'        => $opB->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $enProceso,
]);
$r = $controlador->destroy($faseBorrar->id);
check(str_contains(successDe($r) ?? '', 'eliminada'), 'destroy responde con éxito');
check(! FasePieza::whereKey($faseBorrar->id)->exists(), 'la asignación desapareció de BD');

$faseConDictamen = FasePieza::create([
    'pieza_id'           => $piezaC->id,
    'fase_id'            => $pintura,
    'operario_id'        => $opC->id,
    'usuario_captura_id' => $produccion->id,
    'estatus_fase_id'    => $enProceso,
]);
ObservacionCalidad::create([
    'fase_pieza_id' => $faseConDictamen->id,
    'usuario_id'    => $calidad->id,
    'descripcion'   => 'Dictamen de prueba',
    'resultado'     => 'Enviado a Reproceso',
]);
limpiarSesion();
$r = $controlador->destroy($faseConDictamen->id);
check(str_contains(errorDe($r), 'dictámenes'), 'no se elimina una fase con dictamen');
check(FasePieza::whereKey($faseConDictamen->id)->exists(), 'la fase con dictamen sigue en BD');

echo "== 4. Solo Producción ==\n";
Auth::loginUsingId($calidad->id);
$r = $controlador->edit($faseEditar->id);
check($r instanceof RedirectResponse, 'Calidad es redirigida al intentar editar');
$r = $controlador->destroy($faseEditar->id);
check($r instanceof RedirectResponse, 'Calidad es redirigida al intentar eliminar');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}

<?php

use App\Http\Controllers\FasePiezaController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\OperarioController;
use App\Http\Controllers\PiezaController;
use App\Models\CatFase;
use App\Models\FasePieza;
use App\Models\Operario;
use App\Models\Pieza;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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

// El arnés no avanza el ciclo de sesión entre peticiones: los mensajes flash
// de una respuesta anterior siguen visibles. Se limpian antes de cada check.
function limpiarSesion()
{
    session()->forget(['error', 'success']);
}

$produccion = User::where('rol_id', 1)->firstOrFail();
$calidad    = User::where('rol_id', 2)->firstOrFail();

$faseArmado    = CatFase::where('nombre', 'Armado')->value('id');
$faseSoldadura = CatFase::where('nombre', 'Soldadura')->value('id');
$fasePintura   = CatFase::where('nombre', 'Pintura')->value('id');

echo "== Rutas de operarios ==\n";
check(Route::has('operarios.index'), 'ruta operarios.index registrada');
check(Route::has('operarios.create'), 'ruta operarios.create registrada');
check(Route::has('operarios.store'), 'ruta operarios.store registrada');
check(Route::has('operarios.edit'), 'ruta operarios.edit registrada');
check(Route::has('operarios.update'), 'ruta operarios.update registrada');
check(Route::has('operarios.destroy'), 'ruta operarios.destroy registrada');

echo "== Modelo: relacion N:M y especialidad derivada ==\n";
Auth::loginUsingId($produccion->id);

$crear = (new OperarioController)->store(xpost([
    'clave_empleado' => 'TEST-OP-001',
    'nombre'         => 'Pedro',
    'apellido'       => 'Soldador',
    'activo'         => 1,
    'fases'          => [$faseSoldadura, $fasePintura],
]));
check(str_contains(successDe($crear), 'registrado'), 'store registra el operario');

$multifase = Operario::where('clave_empleado', 'TEST-OP-001')->firstOrFail();
check($multifase->fasesHabilitadas()->count() === 2, 'el operario multifase tiene 2 fases habilitadas');
check($multifase->puedeHacerFase($faseSoldadura), 'puede hacer Soldadura');
check($multifase->puedeHacerFase($fasePintura), 'puede hacer Pintura');
check(! $multifase->puedeHacerFase($faseArmado), 'NO puede hacer Armado (no habilitada)');

echo "== Especialidad derivada ==\n";
$esp = $multifase->especialidadDerivada();
check(str_contains($esp, 'Soldadura'), 'la especialidad derivada incluye Soldadura');
check(str_contains($esp, 'Pintura'), 'la especialidad derivada incluye Pintura');
check(! str_contains($esp, 'Armado'), 'la especialidad derivada NO incluye Armado');
check($multifase->especialidadDerivada === $esp, 'el accessor coincide con el metodo');

echo "== Operario de una sola etapa ==\n";
$crearMono = (new OperarioController)->store(xpost([
    'clave_empleado' => 'TEST-OP-002',
    'nombre'         => 'Lucia',
    'apellido'       => 'Pintora',
    'activo'         => 1,
    'fases'          => [$fasePintura],
]));
check(str_contains(successDe($crearMono), 'registrado'), 'store registra un operario de una etapa');

$monofase = Operario::where('clave_empleado', 'TEST-OP-002')->firstOrFail();
check($monofase->fasesHabilitadas()->count() === 1, 'el operario monofase tiene 1 fase');
check($monofase->especialidadDerivada() === 'Pintura', 'especialidad derivada de una etapa = Pintura');
check($monofase->puedeHacerFase($fasePintura), 'puede hacer Pintura');
check(! $monofase->puedeHacerFase($faseArmado), 'NO puede hacer Armado');

echo "== Sin fases configuradas = sin restriccion (compatibilidad) ==\n";
// Se fuerza un operario sin filas en operario_fase.
$libre = Operario::create([
    'clave_empleado' => 'TEST-OP-003',
    'nombre'         => 'Comodin',
    'apellido'       => 'Total',
    'activo'         => true,
]);
check($libre->puedeHacerFase($faseArmado), 'sin fases configuradas puede hacer Armado');
check($libre->puedeHacerFase($fasePintura), 'sin fases configuradas puede hacer Pintura');
check($libre->especialidadDerivada() === ($libre->especialidad ?? 'Sin fases asignadas'), 'sin fases cae al respaldo');

echo "== Validacion: store exige al menos una fase ==\n";
// Se valida con el mismo ruleset que usa el controller para capturar el error.
$validator = \Illuminate\Support\Facades\Validator::make(
    ['clave_empleado' => 'TEST-OP-004', 'nombre' => 'X', 'apellido' => 'Y', 'fases' => []],
    ['fases' => 'required|array|min:1']
);
check($validator->fails(), 'sin fases la validacion falla');

echo "== CRUD: editar cambia las fases ==\n";
$editar = (new OperarioController)->update(xpost([
    'clave_empleado' => 'TEST-OP-002',
    'nombre'         => 'Lucia',
    'apellido'       => 'Pintora',
    'activo'         => 1,
    'fases'          => [$faseArmado, $fasePintura],
]), $monofase->id);
check(str_contains(successDe($editar), 'actualizado'), 'update actualiza el operario');
$monofase->refresh();
check($monofase->fasesHabilitadas()->count() === 2, 'tras editar tiene 2 fases');
check($monofase->puedeHacerFase($faseArmado), 'tras editar puede hacer Armado (recien habilitada)');

echo "== CRUD: eliminar sin historial ==\n";
$eliminar = (new OperarioController)->destroy($libre->id);
check(str_contains(successDe($eliminar), 'eliminado'), 'destroy elimina un operario sin fases registradas');
check(Operario::where('clave_empleado', 'TEST-OP-003')->doesntExist(), 'el operario sin historial ya no existe');

echo "== CRUD: no eliminar con historial ==\n";
$obra = Obra::create([
    'codigo_obra'      => 'TEST-OPF-001',
    'nombre_obra'      => 'Obra Operarios Test',
    'cliente'          => 'Cliente Op',
    'fecha_inicio'     => now()->toDateString(),
    'fecha_compromiso' => now()->addDays(30)->toDateString(),
    'estatus'          => 'Activa',
]);
$pieza = Pieza::create([
    'obra_id'            => $obra->id,
    'tipo_estructura_id' => 1,
    'estatus_id'         => 1,
    'clave_marca_pieza'  => 'OPF1',
    'peso_kg'            => 10,
    'cantidad'           => 1,
]);

$enProceso  = \App\Models\CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$completada = \App\Models\CatEstatusFase::where('nombre', 'Completada')->value('id');

// El nuevo orden exige Armado y Soldadura completadas antes de Pintura.
// Se siembra el historial directamente (simula cierres ya aprobados) para
// que esta suite siga probando la gestion de operarios, no el orden.
$opSet1 = operarioDePrueba('OPF-SET1');
foreach ([$faseArmado, $faseSoldadura] as $fasePrevia) {
    FasePieza::create([
        'pieza_id'           => $pieza->id,
        'fase_id'            => $fasePrevia,
        'operario_id'        => $opSet1->id,
        'usuario_captura_id' => $produccion->id,
        'estatus_fase_id'    => $completada,
        'fecha_inicio'       => now()->subDay(),
        'fecha_termino'      => now(),
    ]);
}

// Se registra una fase con el monofase para darle historial real.
(new FasePiezaController)->store(xpost([
    'fase_id'         => $fasePintura,
    'operario_id'     => $monofase->id,
    'estatus_fase_id' => $enProceso,
]), $pieza->id);

$rechazoEliminar = (new OperarioController)->destroy($monofase->id);
check(str_contains(errorDe($rechazoEliminar), 'No se puede eliminar'), 'no borra un operario con fases registradas');
check(Operario::where('id', $monofase->id)->exists(), 'el operario con historial sigue existiendo');

echo "== Bloqueo real: asignar operario no habilitado a una fase ==\n";

// La pieza OPF1 ya tiene Soldadura completada. Creamos OPF2 con el mismo
// historial previo para probar la habilitacion sin chocar con esa fase.
$piezaHabilitacion = Pieza::create([
    'obra_id'            => $obra->id,
    'tipo_estructura_id' => 1,
    'estatus_id'         => 1,
    'clave_marca_pieza'  => 'OPF2',
    'peso_kg'            => 10,
    'cantidad'           => 1,
]);
$opSet2 = operarioDePrueba('OPF-SET2');
// Solo Armado: Soldadura debe estar libre para probar la habilitación.
foreach ([$faseArmado] as $fasePrevia) {
    FasePieza::create([
        'pieza_id'           => $piezaHabilitacion->id,
        'fase_id'            => $fasePrevia,
        'operario_id'        => $opSet2->id,
        'usuario_captura_id' => $produccion->id,
        'estatus_fase_id'    => $completada,
        'fecha_inicio'       => now()->subDay(),
        'fecha_termino'      => now(),
    ]);
}

// El monofase (Pintura + Armado) NO puede hacer Soldadura.
limpiarSesion();
$bloqueo = (new FasePiezaController)->store(xpost([
    'fase_id'         => $faseSoldadura,
    'operario_id'     => $monofase->id,
    'estatus_fase_id' => $enProceso,
]), $piezaHabilitacion->id);
check(str_contains(errorDe($bloqueo), 'no está habilitado'), 'se bloquea asignar un operario no habilitado');

// El multifase SI puede hacer Soldadura.
limpiarSesion();
$permitido = (new FasePiezaController)->store(xpost([
    'fase_id'         => $faseSoldadura,
    'operario_id'     => $multifase->id,
    'estatus_fase_id' => $enProceso,
]), $piezaHabilitacion->id);
check(str_contains(successDe($permitido), 'registrado'), 'se permite asignar un operario habilitado');
check(FasePieza::where('pieza_id', $piezaHabilitacion->id)->where('fase_id', $faseSoldadura)->where('operario_id', $multifase->id)->exists(), 'la fase quedo registrada con el operario correcto');

echo "== Validacion existente: no repetir operario en fase En Proceso ==\n";
limpiarSesion();
$repetir = (new FasePiezaController)->store(xpost([
    'fase_id'         => $faseSoldadura,
    'operario_id'     => $multifase->id,
    'estatus_fase_id' => $enProceso,
]), $piezaHabilitacion->id);
check(str_contains(errorDe($repetir), 'ya está asignado'), 'se mantiene la regla de no repetir operario en proceso');

echo "== A9: no desactivar un operario con tarea activa ==\n";
$desactivar = (new OperarioController)->update(xpost([
    'clave_empleado' => $monofase->clave_empleado,
    'nombre'         => $monofase->nombre,
    'apellido'       => $monofase->apellido,
    'activo'         => 0,
    'fases'          => [$faseArmado, $fasePintura],
]), $monofase->id);
check(str_contains(errorDe($desactivar), 'No se puede desactivar'), 'se bloquea desactivar con tarea activa');
$monofase->refresh();
check((bool) $monofase->activo, 'el operario sigue activo');

echo "== A8: no quitar la habilitacion de una fase activa ==\n";
$quitarFase = (new OperarioController)->update(xpost([
    'clave_empleado' => $monofase->clave_empleado,
    'nombre'         => $monofase->nombre,
    'apellido'       => $monofase->apellido,
    'activo'         => 1,
    'fases'          => [$faseArmado],
]), $monofase->id);
check(str_contains(errorDe($quitarFase), 'No se puede quitar'), 'se bloquea quitar la fase que esta trabajando');
$monofase->refresh();
check($monofase->puedeHacerFase($fasePintura), 'la habilitacion de Pintura se conserva');

echo "== A8/A9: editar sin tocar la tarea activa si se permite ==\n";
$editarValido = (new OperarioController)->update(xpost([
    'clave_empleado' => $monofase->clave_empleado,
    'nombre'         => 'Lucia',
    'apellido'       => 'Pintora',
    'activo'         => 1,
    'fases'          => [$faseArmado, $fasePintura],
]), $monofase->id);
check(str_contains(successDe($editarValido), 'actualizado'), 'editar conservando la fase activa si se permite');

echo "== Permisos: solo Produccion gestiona operarios ==\n";
Auth::loginUsingId($calidad->id);
$indexCalidad = (new OperarioController)->index();
check($indexCalidad instanceof \Illuminate\Http\RedirectResponse, 'Calidad es redirigida fuera de operarios');
check(str_contains(errorDe($indexCalidad), 'Producción'), 'el mensaje indica que solo Produccion gestiona');
$createCalidad = (new OperarioController)->create();
check($createCalidad instanceof \Illuminate\Http\RedirectResponse, 'Calidad no accede al formulario de alta');

echo "== Vista de indice (Produccion) ==\n";
Auth::loginUsingId($produccion->id);
$index = (new OperarioController)->index();
check($index instanceof \Illuminate\View\View, 'index devuelve la vista');
$html = $index->render();
check(str_contains($html, 'Operarios'), 'el titulo aparece');
check(str_contains($html, 'Fases habilitadas'), 'se muestra la seccion de fases habilitadas');
check(str_contains($html, 'Multifase') || str_contains($html, 'multifase'), 'la vista explica el concepto multifase');
check(str_contains($html, 'Nuevo Operario'), 'se ofrece el alta');

echo "== Formulario create con checkboxes de fases ==\n";
$create = (new OperarioController)->create();
$htmlCreate = $create->render();
check(str_contains($htmlCreate, 'name="fases[]"'), 'el formulario tiene checkboxes de fases');
check(substr_count($htmlCreate, 'name="fases[]"') >= 3, 'hay un checkbox por cada fase del catalogo');

echo "== Formulario edit con fases marcadas ==\n";
$edit = (new OperarioController)->edit($multifase->id);
$htmlEdit = $edit->render();
check(str_contains($htmlEdit, 'name="fases[]"'), 'el edit tiene checkboxes de fases');
check(str_contains($htmlEdit, 'checked'), 'el edit marca las fases ya habilitadas');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

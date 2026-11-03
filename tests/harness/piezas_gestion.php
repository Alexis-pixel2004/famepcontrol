<?php

use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\CatEstatusFase;
use App\Models\FasePieza;
use App\Models\Obra;
use App\Models\Pieza;
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

// Rol 1 = Produccion (unico que puede editar/eliminar)
Auth::loginUsingId(1);

echo "== Rutas de gestion de piezas ==\n";
check(Route::has('piezas.edit'), 'ruta piezas.edit registrada');
check(Route::has('piezas.update'), 'ruta piezas.update registrada');
check(Route::has('piezas.destroy'), 'ruta piezas.destroy registrada');

echo "== Alta de obra base ==\n";
(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-PZ-001',
    'nombre_obra'      => 'Obra Piezas Test',
    'cliente'          => 'Cliente Piezas',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obra = Obra::where('codigo_obra', 'TEST-PZ-001')->firstOrFail();

// Segunda obra para probar que la marca es unica SOLO dentro de su obra
(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-PZ-002',
    'nombre_obra'      => 'Obra Piezas Test 2',
    'cliente'          => 'Cliente Piezas',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obra2 = Obra::where('codigo_obra', 'TEST-PZ-002')->firstOrFail();

echo "== Editar pieza SIN avance (todo editable) ==\n";
(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'PZ1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 100,
    'cantidad'           => 1,
]), $obra->id);
$pieza = Pieza::where('clave_marca_pieza', 'PZ1')->where('obra_id', $obra->id)->firstOrFail();

$htmlEdit = (new PiezaController)->edit($pieza->id)->render();
check(str_contains($htmlEdit, 'name="clave_marca_pieza"'), 'edit sin avance: marca editable');
check(str_contains($htmlEdit, 'name="tipo_estructura_id"'), 'edit sin avance: tipo editable');
check(str_contains($htmlEdit, 'value="PUT"'), 'edit: formulario con method PUT');
check(str_contains($htmlEdit, route('piezas.update', $pieza->id)), 'edit: action apunta a piezas.update');

(new PiezaController)->update(xpost([
    'clave_marca_pieza'  => 'PZ1X',
    'tipo_estructura_id' => 2,
    'peso_kg'            => 150.5,
    'cantidad'           => 3,
]), $pieza->id);

$pieza->refresh();
check($pieza->clave_marca_pieza === 'PZ1X', 'update sin avance: marca cambiada (normalizada)');
check((int) $pieza->tipo_estructura_id === 2, 'update sin avance: tipo cambiado');
check((float) $pieza->peso_kg === 150.5, 'update sin avance: peso cambiado');
check((int) $pieza->cantidad === 3, 'update sin avance: cantidad cambiada');

echo "== Marca unica por obra ==\n";
(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'PZ2',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 50,
    'cantidad'           => 1,
]), $obra->id);

$marcaDuplicada = false;
try {
    (new PiezaController)->update(xpost([
        'clave_marca_pieza'  => 'PZ2',
        'tipo_estructura_id' => 1,
        'peso_kg'            => 150.5,
        'cantidad'           => 3,
    ]), $pieza->id);
} catch (\Illuminate\Validation\ValidationException $e) {
    $marcaDuplicada = array_key_exists('clave_marca_pieza', $e->errors());
}
check($marcaDuplicada, 'update: marca duplicada en la misma obra rechazada');

// Misma marca en OTRA obra: permitida
(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'PZ1X',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 10,
    'cantidad'           => 1,
]), $obra2->id);
check(
    Pieza::where('clave_marca_pieza', 'PZ1X')->where('obra_id', $obra2->id)->exists(),
    'misma marca permitida en otra obra'
);

echo "== Editar pieza CON avance (solo peso/cantidad) ==\n";
(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'PZ3',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 200,
    'cantidad'           => 2,
]), $obra->id);
$piezaAvance = Pieza::where('clave_marca_pieza', 'PZ3')->where('obra_id', $obra->id)->firstOrFail();

FasePieza::create([
    'pieza_id'           => $piezaAvance->id,
    'fase_id'            => 1,
    'operario_id'        => 1,
    'usuario_captura_id' => 1,
    'estatus_fase_id'    => CatEstatusFase::where('nombre', 'En Proceso')->value('id'),
    'fecha_inicio'       => now(),
]);
$piezaAvance->refresh();

$htmlAvance = (new PiezaController)->edit($piezaAvance->id)->render();
check(! str_contains($htmlAvance, 'name="clave_marca_pieza"'), 'edit con avance: marca NO editable');
check(! str_contains($htmlAvance, 'name="tipo_estructura_id"'), 'edit con avance: tipo NO editable');
check(str_contains($htmlAvance, 'name="peso_kg"') && str_contains($htmlAvance, 'name="cantidad"'), 'edit con avance: peso y cantidad editables');

// Intento de cambiar marca/tipo: se ignoran (aunque vengan en el request)
(new PiezaController)->update(xpost([
    'clave_marca_pieza'  => 'HACKEADA',
    'tipo_estructura_id' => 3,
    'peso_kg'            => 250,
    'cantidad'           => 4,
]), $piezaAvance->id);

$piezaAvance->refresh();
check($piezaAvance->clave_marca_pieza === 'PZ3', 'update con avance: marca NO cambio');
check((int) $piezaAvance->tipo_estructura_id === 1, 'update con avance: tipo NO cambio');
check((float) $piezaAvance->peso_kg === 250.0, 'update con avance: peso SI cambio');
check((int) $piezaAvance->cantidad === 4, 'update con avance: cantidad SI cambio');

echo "== Permisos (solo Produccion) ==\n";
Auth::loginUsingId(2); // Calidad
$resp = (new PiezaController)->edit($pieza->id);
check($resp->getSession()?->get('error') !== null, 'calidad no puede abrir el formulario de edicion');

(new PiezaController)->update(xpost([
    'clave_marca_pieza'  => 'HACK',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 999,
    'cantidad'           => 9,
]), $pieza->id);
$pieza->refresh();
check($pieza->clave_marca_pieza === 'PZ1X', 'calidad no logro modificar la pieza');

$resp = (new PiezaController)->destroy($pieza->id);
check($resp->getSession()?->get('error') !== null, 'calidad no puede eliminar');
check(Pieza::whereKey($pieza->id)->exists(), 'calidad no logro eliminar la pieza');

echo "== Eliminar pieza SIN avance ==\n";
Auth::loginUsingId(1);

// PZ1X ahora tiene 3 piezas en la obra; guardamos el conteo de la obra
$resp = (new PiezaController)->destroy($pieza->id);
check(str_contains(successDe($resp), 'eliminada'), 'destroy: pieza sin avance eliminada con exito');
check(! Pieza::whereKey($pieza->id)->exists(), 'destroy: la pieza ya no existe');

echo "== No eliminar pieza CON avance ==\n";
$resp = (new PiezaController)->destroy($piezaAvance->id);
check(str_contains(errorDe($resp), 'sin fases registradas'), 'destroy: pieza con avance rechazada con mensaje claro');
check(Pieza::whereKey($piezaAvance->id)->exists(), 'destroy: la pieza con avance sigue existiendo');
check($piezaAvance->fases()->count() === 1, 'destroy: la fase de la pieza con avance intacta');

echo "== Recalculo de la obra tras eliminar ==\n";
// Obra sin piezas => Pendiente; con pieza sin avance => Pendiente;
// con avance => Activa. Verificamos que el recalculo corre sin errores.
(new PiezaController)->destroy(
    Pieza::where('clave_marca_pieza', 'PZ2')->where('obra_id', $obra->id)->firstOrFail()->id
);
$obra->refresh();
check($obra->estatus === 'Activa', 'obra con pieza en avance sigue Activa tras eliminar otras');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

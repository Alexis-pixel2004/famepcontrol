<?php

use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\Obra;
use App\Models\Pieza;
use App\Models\User;
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

// Rol 1 = Produccion (unico que puede editar/eliminar)
Auth::loginUsingId(1);

echo "== Rutas de gestion de obras ==\n";
check(\Illuminate\Support\Facades\Route::has('obras.edit'), 'ruta obras.edit registrada');
check(\Illuminate\Support\Facades\Route::has('obras.update'), 'ruta obras.update registrada');
check(\Illuminate\Support\Facades\Route::has('obras.destroy'), 'ruta obras.destroy registrada');

echo "== Editar obra: formulario ==\n";
(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-EDIT-001',
    'nombre_obra'      => 'Obra Editable',
    'cliente'          => 'Cliente X',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));

$obra = Obra::where('codigo_obra', 'TEST-EDIT-001')->firstOrFail();

$htmlEdit = (new ObraController)->edit($obra->id)->render();
check(str_contains($htmlEdit, 'Obra Editable'), 'edit: nombre precargado');
check(str_contains($htmlEdit, 'TEST-EDIT-001'), 'edit: codigo precargado');
check(str_contains($htmlEdit, 'value="PUT"'), 'edit: formulario con method PUT');
check(str_contains($htmlEdit, route('obras.update', $obra->id)), 'edit: action apunta a obras.update');

echo "== Actualizar obra ==\n";
(new ObraController)->update(xpost([
    'codigo_obra'      => 'TEST-EDIT-001', // mismo codigo: no debe chocar con el unique
    'nombre_obra'      => 'Obra Editada',
    'cliente'          => 'Cliente Y',
    'fecha_inicio'     => '2026-10-05',
    'fecha_compromiso' => '2026-12-15',
]), $obra->id);

$obra->refresh();
check($obra->nombre_obra === 'Obra Editada', 'update: nombre cambiado');
check($obra->cliente === 'Cliente Y', 'update: cliente cambiado');
check($obra->fecha_inicio->format('Y-m-d') === '2026-10-05', 'update: fecha_inicio cambiada');

echo "== Validacion al actualizar ==\n";
// Codigo duplicado de OTRA obra => rechazado (la validacion lanza ValidationException)
$codigoDuplicadoRechazado = false;
try {
    (new ObraController)->update(xpost([
        'codigo_obra'      => 'OB-2026-001',
        'nombre_obra'      => 'Obra Editada',
        'cliente'          => 'Cliente Y',
        'fecha_inicio'     => '2026-10-05',
        'fecha_compromiso' => '2026-12-15',
    ]), $obra->id);
} catch (\Illuminate\Validation\ValidationException $e) {
    $codigoDuplicadoRechazado = array_key_exists('codigo_obra', $e->errors());
}
check($codigoDuplicadoRechazado, 'update: codigo duplicado rechazado');

// Fecha compromiso anterior al inicio => rechazado
$fechaInvalidaRechazada = false;
try {
    (new ObraController)->update(xpost([
        'codigo_obra'      => 'TEST-EDIT-001',
        'nombre_obra'      => 'Obra Editada',
        'cliente'          => 'Cliente Y',
        'fecha_inicio'     => '2026-12-31',
        'fecha_compromiso' => '2026-10-01',
    ]), $obra->id);
} catch (\Illuminate\Validation\ValidationException $e) {
    $fechaInvalidaRechazada = array_key_exists('fecha_compromiso', $e->errors());
}
check($fechaInvalidaRechazada, 'update: fecha compromiso anterior rechazada');

$obra->refresh();
check($obra->fecha_inicio->format('Y-m-d') === '2026-10-05', 'update: no se persistio un cambio invalido');

echo "== Permisos (solo Produccion) ==\n";
$obraPendiente = (new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-PERM-001',
    'nombre_obra'      => 'Obra Permisos',
    'cliente'          => 'Cliente Z',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obraPerm = Obra::where('codigo_obra', 'TEST-PERM-001')->firstOrFail();

Auth::loginUsingId(2); // Calidad
$resp = (new ObraController)->edit($obraPerm->id);
check($resp->getSession()?->get('error') !== null, 'calidad no puede abrir el formulario de edicion');

$resp = (new ObraController)->update(xpost([
    'codigo_obra'      => 'TEST-PERM-001',
    'nombre_obra'      => 'Hackeada',
    'cliente'          => 'Z',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]), $obraPerm->id);
check($resp->getSession()?->get('error') !== null, 'calidad no puede actualizar');
$obraPerm->refresh();
check($obraPerm->nombre_obra === 'Obra Permisos', 'calidad no logro modificar la obra');

$resp = (new ObraController)->destroy($obraPerm->id);
check($resp->getSession()?->get('error') !== null, 'calidad no puede eliminar');
check(Obra::whereKey($obraPerm->id)->exists(), 'calidad no logro eliminar la obra');

echo "== Eliminar obra Pendiente (con piezas, cascade) ==\n";
Auth::loginUsingId(1);

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'TP1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 100,
    'cantidad'           => 1,
]), $obraPerm->id);

$piezaPerm = Pieza::where('clave_marca_pieza', 'TP1')->firstOrFail();
check($obraPerm->estatus === 'Pendiente', 'obra con pieza sin avance sigue Pendiente');

$resp = (new ObraController)->destroy($obraPerm->id);
check(str_contains(successDe($resp), 'eliminada'), 'destroy: obra Pendiente eliminada con exito');
check(! Obra::whereKey($obraPerm->id)->exists(), 'destroy: la obra ya no existe');
check(! Pieza::whereKey($piezaPerm->id)->exists(), 'destroy: las piezas se borraron en cascada');

echo "== No eliminar obra con avance (Activa) ==\n";
$obraActiva = Obra::where('estatus', 'Activa')->firstOrFail();
$totalPiezasAntes = $obraActiva->piezas()->count();

$resp = (new ObraController)->destroy($obraActiva->id);
check(str_contains(errorDe($resp), 'Pendientes'), 'destroy: obra Activa rechazada con mensaje claro');
check(Obra::whereKey($obraActiva->id)->exists(), 'destroy: la obra Activa sigue existiendo');
check($obraActiva->piezas()->count() === $totalPiezasAntes, 'destroy: las piezas de la obra Activa intactas');

echo "== La obra eliminada ya no aparece en el portal del cliente ==\n";
$obraPortal = (new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-PORTAL-001',
    'nombre_obra'      => 'Obra Portal',
    'cliente'          => 'Cliente Portal',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obraPortal = Obra::where('codigo_obra', 'TEST-PORTAL-001')->firstOrFail();

$cliente = User::create([
    'nombre' => 'Cliente Portal Test',
    'email' => 'portal-test@famep.com',
    'password_hash' => bcrypt('x'),
    'rol_id' => 3,
    'activo' => true,
    'codigo_acceso' => 'FAMEP-TST1',
]);
$obraPortal->update(['cliente_usuario_id' => $cliente->id]);

$htmlPortal = (new \App\Http\Controllers\ClienteController)->ver('FAMEP-TST1')->render();
check(str_contains($htmlPortal, 'Obra Portal'), 'portal: obra visible antes de eliminar');

(new ObraController)->destroy($obraPortal->id);

$htmlPortalDespues = (new \App\Http\Controllers\ClienteController)->ver('FAMEP-TST1')->render();
check(! str_contains($htmlPortalDespues, 'Obra Portal'), 'portal: obra eliminada ya no se muestra');

echo "== B5: cliente_usuario_id debe ser rol 3 ==\n";

$invalido = null;
try {
    (new ObraController)->store(xpost([
        'codigo_obra'        => 'TEST-PORTAL-002',
        'nombre_obra'        => 'Obra con jefe',
        'cliente'            => 'Cliente X',
        'fecha_inicio'       => '2026-10-01',
        'fecha_compromiso'   => '2026-12-31',
        'cliente_usuario_id' => Auth::id(), // Producción, rol 1
    ]));
} catch (\Illuminate\Validation\ValidationException $e) {
    $invalido = $e;
}
check($invalido !== null, 'store: rechaza un usuario que no es cliente');
check($invalido !== null && array_key_exists('cliente_usuario_id', $invalido->errors()), 'store: el error apunta a cliente_usuario_id');
check(Obra::where('codigo_obra', 'TEST-PORTAL-002')->doesntExist(), 'store: no crea la obra invalida');

$clienteValido = User::create([
    'nombre' => 'Cliente Rol Tres',
    'email' => 'rol3-obras@famep.com',
    'password_hash' => bcrypt('x'),
    'rol_id' => 3,
    'activo' => true,
]);
(new ObraController)->store(xpost([
    'codigo_obra'        => 'TEST-PORTAL-003',
    'nombre_obra'        => 'Obra con cliente valido',
    'cliente'            => 'Cliente X',
    'fecha_inicio'       => '2026-10-01',
    'fecha_compromiso'   => '2026-12-31',
    'cliente_usuario_id' => $clienteValido->id,
]));
check((int) Obra::where('codigo_obra', 'TEST-PORTAL-003')->value('cliente_usuario_id') === (int) $clienteValido->id, 'store: acepta un cliente rol 3');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

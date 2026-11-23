<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ObraController;
use App\Http\Middleware\EnsureJefatura;
use App\Models\LogAuditoria;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

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

echo "== Rutas registradas ==\n";
check(Route::has('consulta.acceso') && Route::has('consulta.buscar') && Route::has('consulta.ver') && Route::has('consulta.obra'), 'rutas publicas consulta registradas');
check(Route::has('clientes.index') && Route::has('clientes.create') && Route::has('clientes.store'), 'rutas clientes (admin) registradas');

echo "== Alta de cliente (pantalla de clientes) ==\n";
// La base de desarrollo puede tener la obra 1 ya asignada; se libera dentro
// de la transacción para que la prueba no dependa del estado real.
Obra::whereKey(1)->update(['cliente_usuario_id' => null]);

$req = Request::create('/clientes', 'POST', [
    'nombre' => 'Carlos Vizcaíno',
    'obras' => ['1'],
]);
$respuesta = (new ClienteController)->store($req);
$cliente = User::where('email', $req['email'] ?? '')
    ->orWhere('nombre', 'Carlos Vizcaíno')
    ->where('rol_id', 3)
    ->orderBy('id', 'desc')
    ->first();

check($cliente !== null, 'usuario rol 3 creado por store');
check($cliente?->esCliente() === true, 'esCliente() === true para rol 3');
check((bool) preg_match('/^FAMEP-[A-Z0-9]{4}$/', $cliente?->codigo_acceso), 'codigo auto genera formato FAMEP-XXXX');
check(str_contains($cliente?->email ?? '', '@famep.com'), 'email sintetico generado cuando no se envia');
check((int) Obra::find(1)->cliente_usuario_id === (int) $cliente->id, 'obra 1 asignada al cliente');
check(str_contains($respuesta->getSession()?->get('success') ?? '', $cliente?->codigo_acceso), 'flash success muestra el codigo una sola vez');

$req2 = Request::create('/clientes', 'POST', [
    'nombre' => 'María López',
    'email' => 'maria@constructora.mx',
    'obras' => [],
]);
(new ClienteController)->store($req2);
$maria = User::where('email', 'maria@constructora.mx')->first();
check($maria !== null && $maria->esCliente(), 'cliente con email propio se respeta');
check(! str_contains($maria->email, 'famep.com'), 'no se reemplaza el email proporcionado');
check((int) Obra::find(1)->cliente_usuario_id === (int) $cliente->id, 'obras no enviadas no cambian de dueño');

echo "== B4: no reasignar en silencio obras de otro cliente ==\n";
$req3 = Request::create('/clientes', 'POST', [
    'nombre' => 'Cliente Intruso',
    'email'  => 'intruso@famep.com',
    'obras'  => ['1'],
]);
$resp3 = (new ClienteController)->store($req3);
$error3 = (string) ($resp3->getSession()?->get('error') ?? '');

check(User::where('email', 'intruso@famep.com')->doesntExist(), 'B4: no crea el cliente si hay conflicto');
check((int) Obra::find(1)->cliente_usuario_id === (int) $cliente->id, 'B4: la obra conserva a su dueño');
check(str_contains($error3, 'ya pertenecen a otro cliente'), 'B4: el mensaje explica el conflicto');
check(str_contains($error3, 'OB-2026-001'), 'B4: el mensaje nombra la obra');

echo "== Acceso por codigo ==\n";
$form = (new ClienteController)->acceso()->render();
check(str_contains($form, 'Código de acceso'), 'portal.acceso: formulario de codigo');
check(preg_match('/<form[^>]+action="[^"]*\/consulta"[^>]+method="POST"/', $form) === 1, 'portal.acceso: envia por POST a la ruta de busqueda');

$invalido = (new ClienteController)->ver('FAMEP-0000');
check($invalido instanceof \Illuminate\Http\RedirectResponse, 'codigo inexistente redirige');
check($invalido->getTargetUrl() === route('consulta.acceso'), 'codigo inexistente va a acceso de nuevo');
check(str_contains($invalido->getSession()?->get('error') ?? '', 'no está activo o no existe'), 'codigo inexistente muestra error claro');

$vista = (new ClienteController)->ver($cliente->codigo_acceso);
check($vista instanceof View, 'codigo valido devuelve vista');
$html = $vista->render();
check(str_contains($html, 'Carlos Vizcaíno'), 'portal.index: saluda al cliente');
check(str_contains($html, 'OB-2026-001'), 'portal.index: lista la obra asignada');
check(str_contains($html, 'En fabricación'), 'portal.index: obra Activa en lenguaje llano');
check(! str_contains($html, 'OB-PRUEBA'), 'portal.index: NO muestra obras de otra cuenta');

echo "== Detalle por marca y fase (authorizacion por obra) ==\n";

$detalle = (new ClienteController)->obra($cliente->codigo_acceso, 1);
check($detalle instanceof View, 'detalle de obra propia devuelve vista');
$dhtml = $detalle->render();
check(str_contains($dhtml, 'TB1'), 'portal.show: muestra la marca TB1');
check(str_contains($dhtml, 'Armado'), 'portal.show: muestra la fase Armado');
check(str_contains($dhtml, 'Soldadura'), 'portal.show: muestra la fase Soldadura');
check(str_contains($dhtml, 'Terminada') || str_contains($dhtml, 'En fabricación'), 'portal.show: estados en lenguaje llano');

$try = null;
try {
    (new ClienteController)->obra($cliente->codigo_acceso, 9999);
} catch (Throwable $e) {
    $try = $e;
}
check($try instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException, 'obra inexistente responde 404');

$try2 = null;
try {
    (new ClienteController)->obra($maria->codigo_acceso, 1);
} catch (Throwable $e) {
    $try2 = $e;
}
check($try2 instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException, 'un cliente NO ve la obra de otro (404)');

echo "== Proteccion de rutas admin (middleware jefatura) ==\n";
$proximo = fn ($req) => new \Symfony\Component\HttpFoundation\Response('NEXT');

$reqJefe = Request::create('/dashboard', 'GET');
$reqJefe->setUserResolver(fn () => User::find(1));
$jefe = (new EnsureJefatura)->handle($reqJefe, $proximo);
check($jefe->getContent() === 'NEXT', 'jefe autenticado pasa el middleware');

$reqCliente = Request::create('/dashboard', 'GET');
$reqCliente->setUserResolver(fn () => User::find($cliente->id));
$bloqueo = (new EnsureJefatura)->handle($reqCliente, $proximo);
check($bloqueo instanceof \Illuminate\Http\RedirectResponse, 'rol 3 redirige fuera del dashboard');
check($bloqueo->getTargetUrl() === route('consulta.acceso'), 'rol 3 va al portal de consulta');

Auth::loginUsingId(1);
$v = (new ObraController)->create()->render();
check(str_contains($v, 'cliente_usuario_id'), 'obras.create: selector de cliente portal');
check(str_contains($v, 'Sin acceso portal'), 'obras.create: opcion sin acceso portal');

$v = (new ClienteController)->index()->render();
check(str_contains($v, 'Clientes'), 'clientes.index: lista de clientes');
check(str_contains($v, 'FAMEP-'), 'clientes.index: muestra codigos de acceso');

$v = (new ClienteController)->create()->render();
check(str_contains($v, 'Registrar Cliente'), 'clientes.create: formulario');

echo "== Gestion del codigo de acceso (admin) ==\n";
check(
    Route::has('clientes.edit') && Route::has('clientes.update') && Route::has('clientes.destroy'),
    'rutas clientes edit/update/destroy registradas'
);

$v = (new ClienteController)->edit($cliente->id)->render();
check(str_contains($v, 'Editar Cliente'), 'clientes.edit: titulo');
check(str_contains($v, 'Código activo'), 'clientes.edit: etiqueta del estado');
check(str_contains($v, 'name="activo"'), 'clientes.edit: campo activo');
check(str_contains($v, $cliente->codigo_acceso), 'clientes.edit: codigo en solo lectura');
check(str_contains($v, route('clientes.update', $cliente->id)), 'clientes.edit: action a clientes.update');

$v = (new ClienteController)->index()->render();
check(str_contains($v, 'Estado') && str_contains($v, 'Acciones'), 'clientes.index: columnas Estado y Acciones');
check(str_contains($v, route('clientes.edit', $cliente->id)), 'clientes.index: enlace Editar');
check(str_contains($v, route('clientes.destroy', $cliente->id)), 'clientes.index: formulario Eliminar');
check(str_contains($v, 'Activo'), 'clientes.index: chip de estado');

// Solo se puede gestionar una cuenta de cliente (rol 3).
$resp = (new ClienteController)->edit(1);
check($resp instanceof \Illuminate\Http\RedirectResponse, 'edit: rechaza un usuario que no es cliente');
check(str_contains($resp->getSession()?->get('error') ?? '', 'no existe'), 'edit: error claro');

$auditoriasAntes = LogAuditoria::where('tabla_afectada', 'usuarios')
    ->where('registro_id', $cliente->id)
    ->count();

$resp = (new ClienteController)->update(
    Request::create('/clientes/' . $cliente->id, 'PUT', ['activo' => 0]),
    $cliente->id
);
check(str_contains($resp->getSession()?->get('success') ?? '', 'desactivado'), 'update: confirma desactivacion');
$cliente->refresh();
check((bool) $cliente->activo === false, 'update: activo queda en false');

$acceso = (new ClienteController)->ver($cliente->codigo_acceso);
check($acceso instanceof \Illuminate\Http\RedirectResponse, 'codigo inactivo redirige del portal');
check(str_contains($acceso->getSession()?->get('error') ?? '', 'no está activo o no existe'), 'codigo inactivo muestra error');
check(
    LogAuditoria::where('tabla_afectada', 'usuarios')
        ->where('registro_id', $cliente->id)
        ->where('accion', 'editado')
        ->count() === $auditoriasAntes + 1,
    'update: registra la auditoria del cambio'
);

$resp = (new ClienteController)->update(
    Request::create('/clientes/' . $cliente->id, 'PUT', ['activo' => 1]),
    $cliente->id
);
$cliente->refresh();
check((bool) $cliente->activo === true, 'update: reactiva el codigo');
check((new ClienteController)->ver($cliente->codigo_acceso) instanceof View, 'codigo reactivado vuelve a dar acceso');

$resp = (new ClienteController)->destroy($cliente->id);
check(User::find($cliente->id) !== null, 'destroy: no elimina cliente con obra asignada');
check(str_contains($resp->getSession()?->get('error') ?? '', 'obras asignadas'), 'destroy: explica el bloqueo');

// María no tiene obras: se puede eliminar y su código deja de funcionar.
$mariaId     = (int) $maria->id;
$codigoMaria = (string) $maria->codigo_acceso;
$auditoriasMaria = LogAuditoria::where('tabla_afectada', 'usuarios')
    ->where('registro_id', $mariaId)
    ->where('accion', 'eliminado')
    ->count();

$resp = (new ClienteController)->destroy($mariaId);
check(User::find($mariaId) === null, 'destroy: elimina cliente sin obras');
check(str_contains($resp->getSession()?->get('success') ?? '', 'ya no es válido'), 'destroy: confirma que el codigo murio');
check((new ClienteController)->ver($codigoMaria) instanceof \Illuminate\Http\RedirectResponse, 'codigo del cliente eliminado no da acceso');
check(
    LogAuditoria::where('tabla_afectada', 'usuarios')
        ->where('registro_id', $mariaId)
        ->where('accion', 'eliminado')
        ->count() === $auditoriasMaria + 1,
    'destroy: registra la auditoria de eliminacion'
);

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}
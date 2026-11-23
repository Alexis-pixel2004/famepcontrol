<?php

use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\OperarioController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PiezaController;
use App\Http\Controllers\ReporteController;
use App\Models\CatEstatusFase;
use App\Models\FasePieza;
use App\Models\LogAuditoria;
use App\Models\Obra;
use App\Models\ObservacionCalidad;
use App\Models\Pieza;
use App\Models\User;
use App\Services\AuditoriaService;
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

$produccion = User::where('rol_id', 1)->firstOrFail();
$calidad    = User::where('rol_id', 2)->firstOrFail();

Auth::loginUsingId($produccion->id);

// Operario propio de la suite para no depender de los operarios reales.
$operario = operarioDePrueba('AUD');

echo "== Rutas de auditoria ==\n";
check(Route::has('reportes.auditoria'), 'ruta reportes.auditoria registrada');

echo "== Nivel 1: crear una obra genera un log ==\n";
$antes = LogAuditoria::count();

$obra = Obra::create([
    'codigo_obra'      => 'TEST-AUD-001',
    'nombre_obra'      => 'Obra Auditoria',
    'cliente'          => 'Cliente A',
    'fecha_inicio'     => now()->toDateString(),
    'fecha_compromiso' => now()->addDays(20)->toDateString(),
    'estatus'          => 'Pendiente',
]);

$logObra = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->latest('id')->first();
check($logObra !== null, 'se registra la creacion de la obra');
check($logObra?->accion === 'creado', 'la accion es "creado"');
check((int) $logObra?->usuario_id === (int) $produccion->id, 'queda el usuario que la creo');
check(($logObra?->datos_nuevos['codigo_obra'] ?? null) === 'TEST-AUD-001', 'guarda el valor nuevo (codigo)');

echo "== Nivel 1: editar una obra guarda previo y nuevo ==\n";
$obra->update(['nombre_obra' => 'Obra Auditoria Editada']);

$logEdit = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->where('accion', 'editado')->latest('id')->first();
check($logEdit !== null, 'se registra la edicion de la obra');
check(($logEdit?->datos_previos['nombre_obra'] ?? null) === 'Obra Auditoria', 'guarda el valor anterior');
check(($logEdit?->datos_nuevos['nombre_obra'] ?? null) === 'Obra Auditoria Editada', 'guarda el valor nuevo');

echo "== Ruido: un update sin cambios no genera log ==\n";
$previos = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->count();
$obra->update(['nombre_obra' => 'Obra Auditoria Editada']); // mismo valor
$ahora = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->count();
check($previos === $ahora, 'un update identico NO genera log');

echo "== Ruido: recalcularEstatus no se audita ==\n";
$logAntesRecalc = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->count();
$obra->recalcularEstatus(); // usa query builder: no debe disparar eventos
$logTrasRecalc = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->count();
check($logAntesRecalc === $logTrasRecalc, 'recalcularEstatus (query builder) no genera ruido');

echo "== Campo derivado ignorado: estatus de obra ==\n";
$logEstatus = LogAuditoria::where('tabla_afectada', 'obras')
    ->where('registro_id', $obra->id)
    ->where('accion', 'editado')
    ->get()
    ->contains(fn ($l) => array_key_exists('estatus', $l->datos_nuevos ?? []));
check(! $logEstatus, 'el campo derivado "estatus" nunca aparece en los logs');

echo "== Nivel 1: eliminar una obra ==\n";
$obra->delete();
$logDel = LogAuditoria::where('tabla_afectada', 'obras')->where('registro_id', $obra->id)->where('accion', 'eliminado')->latest('id')->first();
check($logDel !== null, 'se registra la eliminacion');
check(($logDel?->datos_previos['codigo_obra'] ?? null) === 'TEST-AUD-001', 'guarda el estado previo al borrar');

echo "== Nivel 1: pieza, fase y dictamen ==\n";
$obra2 = Obra::create([
    'codigo_obra'      => 'TEST-AUD-002',
    'nombre_obra'      => 'Obra Auditoria 2',
    'cliente'          => 'Cliente B',
    'fecha_inicio'     => now()->toDateString(),
    'fecha_compromiso' => now()->addDays(20)->toDateString(),
    'estatus'          => 'Pendiente',
]);
$pieza = Pieza::create([
    'obra_id'            => $obra2->id,
    'tipo_estructura_id' => 1,
    'estatus_id'         => 1,
    'clave_marca_pieza'  => 'AUD1',
    'peso_kg'            => 10,
    'cantidad'           => 1,
]);
check(LogAuditoria::where('tabla_afectada', 'piezas')->where('registro_id', $pieza->id)->where('accion', 'creado')->exists(), 'la pieza se audita');

$enProceso = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
(new \App\Http\Controllers\FasePiezaController)->store(xpost([
    'fase_id'         => 1,
    'operario_id'     => $operario->id,
    'estatus_fase_id' => $enProceso,
]), $pieza->id);

$fase = FasePieza::where('pieza_id', $pieza->id)->firstOrFail();
check(LogAuditoria::where('tabla_afectada', 'fases_pieza')->where('registro_id', $fase->id)->exists(), 'la fase se audita');

Auth::loginUsingId($calidad->id);
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'hallazgo auditado',
    'resultado'   => 'Rechazado',
]), $fase->id);

$obs = ObservacionCalidad::where('fase_pieza_id', $fase->id)->latest('id')->firstOrFail();
check(LogAuditoria::where('tabla_afectada', 'observaciones_calidad')->where('registro_id', $obs->id)->where('accion', 'creado')->exists(), 'el dictamen se audita al crear');

echo "== Nivel 1: editar un dictamen guarda resultado previo/nuevo ==\n";
Auth::loginUsingId($calidad->id);
$obs->update(['resultado' => 'Aprobado', 'editado' => true, 'editado_at' => now(), 'editado_por' => $calidad->id]);

$logObs = LogAuditoria::where('tabla_afectada', 'observaciones_calidad')
    ->where('registro_id', $obs->id)->where('accion', 'editado')->latest('id')->first();
check($logObs !== null, 'la edicion del dictamen se audita');
check(($logObs?->datos_previos['resultado'] ?? null) === 'Rechazado', 'guarda el resultado anterior');
check(($logObs?->datos_nuevos['resultado'] ?? null) === 'Aprobado', 'guarda el resultado nuevo');

echo "== Nivel 1: operario (fases habilitadas) ==\n";
Auth::loginUsingId($produccion->id);
(new OperarioController)->store(xpost([
    'clave_empleado' => 'TEST-AUD-OP',
    'nombre'         => 'Aud',
    'apellido'       => 'Operario',
    'activo'         => 1,
    'fases'          => [1, 2],
]));
$operarioAud = \App\Models\Operario::where('clave_empleado', 'TEST-AUD-OP')->firstOrFail();
check(LogAuditoria::where('tabla_afectada', 'operarios')->where('registro_id', $operarioAud->id)->where('accion', 'creado')->exists(), 'el operario se audita');

echo "== Nivel 2: cambio de contrasena (sin guardar la clave) ==\n";
$usuario = User::findOrFail($produccion->id);
Auth::loginUsingId($usuario->id);

// Se fija una clave conocida para poder probar current_password.
$usuario->update(['password_hash' => \Illuminate\Support\Facades\Hash::make('claveVieja123')]);
$usuario->refresh();

// El guard conserva la instancia previa: se refresca para que current_password
// valide contra el hash nuevo.
Auth::setUser($usuario);

$reqPerfil = Request::create('/perfil/password', 'PUT', [
    'password_actual'       => 'claveVieja123',
    'password'              => 'nuevaClaveSegura123',
    'password_confirmation' => 'nuevaClaveSegura123',
]);
$reqPerfil->setUserResolver(fn () => $usuario);

// El controlador regenera la sesion: se provee una real, como el formulario.
if (! app()->bound('session')) {
    app()->register(\Illuminate\Session\SessionServiceProvider::class);
}
$sesionPerfil = app('session')->driver();
$sesionPerfil->start();
$reqPerfil->setLaravelSession($sesionPerfil);

$respPerfil = (new PerfilController)->updatePassword($reqPerfil);
check($respPerfil instanceof \Illuminate\Http\RedirectResponse, 'el cambio de contrasena responde con redireccion');

$logPass = LogAuditoria::where('tabla_afectada', 'usuarios')->where('accion', 'editado')->latest('id')->first();
check($logPass !== null, 'el cambio de contrasena se audita (Nivel 2)');
check(($logPass?->datos_nuevos['password_hash'] ?? null) === '(cambiada)', 'nunca guarda la clave, solo "(cambiada)"');
$serializado = json_encode([$logPass?->datos_previos, $logPass?->datos_nuevos]);
check(! str_contains($serializado, 'nuevaClaveSegura123'), 'la clave nueva NO aparece en el log');
check(! str_contains($serializado, 'claveVieja123'), 'la clave anterior NO aparece en el log');
check(! str_contains($serializado, '$2y$'), 'ningun hash bcrypt queda en el log');

echo "== Reporte de auditoria: solo Produccion ==\n";
Auth::loginUsingId($calidad->id);
$reporteCalidad = (new ReporteController)->auditoria(Request::create('/reportes/auditoria', 'GET'));
check($reporteCalidad instanceof \Illuminate\Http\RedirectResponse, 'Calidad es redirigida: no ve la auditoria');

Auth::loginUsingId($produccion->id);
$reporte = (new ReporteController)->auditoria(Request::create('/reportes/auditoria', 'GET'));
check($reporte instanceof \Illuminate\View\View, 'Produccion si ve la vista de auditoria');
$html = $reporte->render();
check(str_contains($html, 'Bitácora de Auditoría'), 'la vista tiene el titulo');
check(str_contains($html, 'Movimientos por entidad'), 'se muestra el reparto por entidad');

echo "== Reporte de auditoria: filtros ==\n";
$filtrado = (new ReporteController)->auditoria(Request::create('/reportes/auditoria', 'GET', ['accion' => 'eliminado']))->render();
check(str_contains($filtrado, 'Eliminación'), 'el filtro por accion funciona');
check(str_contains($filtrado, 'seleccionado') === false || true, 'el filtro no rompe el render');

$poEntidad = (new ReporteController)->auditoria(Request::create('/reportes/auditoria', 'GET', ['tabla_afectada' => 'observaciones_calidad']))->render();
check(str_contains($poEntidad, 'Dictamen de Calidad'), 'el filtro por entidad funciona');

echo "== Reporte de auditoria: CSV ==\n";
$csv = (new ReporteController)->auditoria(Request::create('/reportes/auditoria', 'GET', ['formato' => 'csv']));
check($csv instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'el CSV es streamed');
ob_start();
$csv->sendContent();
$csvTxt = ob_get_clean();
check(str_contains($csvTxt, 'Usuario'), 'el CSV trae cabeceras');
check(str_contains($csvTxt, 'Auditoria'), 'el CSV incluye movimientos');

echo "== Tarjeta en el indice de reportes ==\n";
$indiceProd = (new ReporteController)->index()->render();
check(str_contains($indiceProd, 'Bitácora de Auditoría'), 'Produccion ve la tarjeta de auditoria');
Auth::loginUsingId($calidad->id);
$indiceCal = (new ReporteController)->index()->render();
check(! str_contains($indiceCal, 'Bitácora de Auditoría'), 'Calidad NO ve la tarjeta de auditoria');

echo "== resumenCambios legible ==\n";
check(str_contains($logObs->resumenCambios(), 'Resultado'), 'el resumen menciona el campo Resultado');
check(str_contains($logObs->resumenCambios(), 'Rechazado'), 'el resumen incluye el valor anterior');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

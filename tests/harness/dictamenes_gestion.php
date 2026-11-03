<?php

use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\CatEstatusFase;
use App\Models\EvidenciaFotografica;
use App\Models\FasePieza;
use App\Models\ObservacionCalidad;
use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

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

// Simula un POST multipart con archivo adjunto (como el formulario real).
function xpostConArchivo($data = [], $archivo = true)
{
    $files = [];
    if ($archivo) {
        $files['foto'] = UploadedFile::fake()->create('evidencia.jpg', 10, 'image/jpeg');
    }

    return Request::create('/x', 'POST', $data, [], $files);
}

function errorDe($resp)
{
    return (string) ($resp->getSession()?->get('error') ?? '');
}

function successDe($resp)
{
    return (string) ($resp->getSession()?->get('success') ?? '');
}

// RF-33 usa dispatchSync sobre un Job que hace Http::post: interceptamos
// la llamada HTTP para no salir a la red durante la suite.
config(['services.n8n.notify_phone' => '5215512345678', 'services.n8n.webhook_url' => 'https://n8n.test/webhook']);
Http::fake(['*' => Http::response(['ok' => true], 200)]);

Storage::fake('public');

echo "== Rutas de gestion de dictamenes ==\n";
check(Route::has('calidad.edit'), 'ruta calidad.edit registrada');
check(Route::has('calidad.update'), 'ruta calidad.update registrada');
check(Route::has('calidad.destroy'), 'ruta calidad.destroy registrada');

echo "== Alta de datos base ==\n";
Auth::loginUsingId(1); // Produccion: crea obra/pieza/fase
(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-DC-001',
    'nombre_obra'      => 'Obra Dictamen Test',
    'cliente'          => 'Cliente Dictamen',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obra = Obra::where('codigo_obra', 'TEST-DC-001')->firstOrFail();

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'DC1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 100,
    'cantidad'           => 1,
]), $obra->id);
$pieza = Pieza::where('clave_marca_pieza', 'DC1')->where('obra_id', $obra->id)->firstOrFail();

$enProceso = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$fase = FasePieza::create([
    'pieza_id'           => $pieza->id,
    'fase_id'            => 1,
    'operario_id'        => 1,
    'usuario_captura_id' => 1,
    'estatus_fase_id'    => $enProceso,
    'fecha_inicio'       => now(),
]);
check($fase->exists, 'fase base creada');

echo "== store liga la evidencia al dictamen (consistencia) ==\n";
Auth::loginUsingId(2); // Calidad
(new ObservacionCalidadController)->store(xpostConArchivo([
    'descripcion' => 'detalle original',
    'resultado'   => 'Rechazado',
]), $fase->id);

$dictamen = $fase->observaciones()->orderByDesc('id')->first();
check($dictamen !== null, 'dictamen creado');
check($dictamen->evidencias()->count() === 1, 'la evidencia quedo ligada al dictamen');
check(
    (int) $dictamen->evidencias()->first()->observacion_calidad_id === (int) $dictamen->id,
    'observacion_calidad_id apunta al dictamen'
);
$fase->refresh();
check($fase->estatusFase?->nombre === 'Rechazada', 'la fase quedo Rechazada tras el store');

echo "== auditoria: un dictamen nuevo NO esta marcado como editado ==\n";
check($dictamen->fueEditado() === false, 'store: editado = false');
check($dictamen->fechaEdicion() === null, 'store: editado_at = null');
check($dictamen->editado_por === null, 'store: editado_por = null');

echo "== edit: formulario de edicion (solo Calidad) ==\n";
$html = (new ObservacionCalidadController)->edit($dictamen->id)->render();
check(str_contains($html, route('calidad.update', $dictamen->id)), 'action apunta a calidad.update');
check(str_contains($html, 'value="PUT"'), 'formulario con method PUT');
check(str_contains($html, 'enctype="multipart/form-data"'), 'formulario multipart');
check(str_contains($html, 'name="descripcion"'), 'campo descripcion');
check(str_contains($html, 'name="resultado"'), 'campo resultado');
check(str_contains($html, 'name="foto"'), 'campo para subir foto');
check(str_contains($html, 'name="quitar_foto[]"'), 'checkbox para quitar foto');
check(str_contains($html, 'detalle original'), 'descripcion precargada');

echo "== update: descripcion, resultado y recalculo de fase ==\n";
$fotoVieja = $dictamen->evidencias()->first();
$resp = (new ObservacionCalidadController)->update(xpostConArchivo([
    'descripcion' => 'detalle editado',
    'resultado'   => 'Enviado a Reproceso',
    'quitar_foto' => [$fotoVieja->id],
], true), $dictamen->id);

$dictamen->refresh();
$fase->refresh();
check($dictamen->descripcion === 'detalle editado', 'update cambia la descripcion');
check($dictamen->resultado === 'Enviado a Reproceso', 'update cambia el resultado');
check($fase->estatusFase?->nombre === 'En Reproceso', 'update recalcula la fase a En Reproceso');
check(str_contains(successDe($resp), 'actualizado'), 'update responde con mensaje de exito');

echo "== auditoria: el dictamen editado queda marcado ==\n";
check($dictamen->fueEditado() === true, 'update: editado = true');
check($dictamen->fechaEdicion() !== null, 'update: editado_at tiene valor');
check((int) $dictamen->editado_por === 2, 'update: editado_por = usuario de Calidad (2)');

$evidenciasNuevas = $dictamen->evidencias()->get();
check($evidenciasNuevas->count() === 1, 'update deja 1 foto (quito la vieja y subio una nueva)');
check(! EvidenciaFotografica::whereKey($fotoVieja->id)->exists(), 'la foto marcada se elimino de la BD');
check(Storage::disk('public')->exists($evidenciasNuevas->first()->ruta_archivo), 'la foto nueva existe en storage');

echo "== update: cambio a Aprobado (recalcula a Aprobada por Calidad) ==\n";
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'ok',
    'resultado'   => 'Aprobado',
]), $dictamen->id);
$dictamen->refresh();
$fase->refresh();
check($fase->estatusFase?->nombre === 'Aprobada por Calidad', 'update recalcula la fase a Aprobada por Calidad');

echo "== Ultimo dictamen manda (recalculo) ==\n";
// Reiniciamos la fase a En Proceso para poder registrar dictamenes nuevos
// (store solo admite dictamen en En Proceso / En Reproceso).
$fase->observaciones()->delete();
$fase->update(['estatus_fase_id' => $enProceso]);
$fase->refresh();

// Dictamen 1: Aprobado  -> Aprobada por Calidad
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'dictamen 1',
    'resultado'   => 'Aprobado',
]), $fase->id);
$dictUno = $fase->observaciones()->orderByDesc('id')->first();
$fase->refresh();
check($fase->estatusFase?->nombre === 'Aprobada por Calidad', 'dictamen 1 (Aprobado) deja la fase Aprobada por Calidad');

// Volvemos a En Proceso y creamos el Dictamen 2: Rechazado (pasa a ser el vigente)
$fase->update(['estatus_fase_id' => $enProceso]);
$fase->refresh();
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'dictamen 2',
    'resultado'   => 'Rechazado',
]), $fase->id);
$dictDos = $fase->observaciones()->orderByDesc('id')->first();
$fase->refresh();
check($fase->estatusFase?->nombre === 'Rechazada', 'dictamen 2 (Rechazado) pasa a ser el vigente');

// Editar el PRIMER dictamen no debe mover el estado: manda el ultimo.
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'dictamen 1 editado',
    'resultado'   => 'Enviado a Reproceso',
]), $dictUno->id);
$fase->refresh();
check($fase->estatusFase?->nombre === 'Rechazada', 'editar un dictamen no vigente no cambia el estado (manda el ultimo)');

echo "== destroy: recalcula y borra archivos fisicos ==\n";
// Borramos el vigente (Rechazado): el estado debe volver a lo que diga el
// primer dictamen, que ahora es Enviado a Reproceso => En Reproceso.
$resp = (new ObservacionCalidadController)->destroy($dictDos->id);
check(str_contains(successDe($resp), 'eliminado'), 'destroy responde con mensaje de exito');
check(! ObservacionCalidad::whereKey($dictDos->id)->exists(), 'destroy borra el dictamen');
$fase->refresh();
check($fase->estatusFase?->nombre === 'En Reproceso', 'destroy recalcula la fase segun el dictamen vigente que queda');

echo "== destroy: sin dictamenes => la fase vuelve a En Proceso ==\n";
$fotoPrimero = $dictUno->evidencias()->first();
$rutaPrimero = $fotoPrimero?->ruta_archivo;

(new ObservacionCalidadController)->destroy($dictUno->id);
$fase->refresh();
check($fase->estatusFase?->nombre === 'En Proceso', 'sin dictamenes la fase vuelve a En Proceso');
check(! ObservacionCalidad::where('fase_pieza_id', $fase->id)->exists(), 'la fase quedo sin dictamenes');
if ($rutaPrimero !== null) {
    check(! Storage::disk('public')->exists($rutaPrimero), 'destroy borra el archivo fisico de la evidencia');
}

echo "== Permisos: solo Calidad puede editar/eliminar ==\n";
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'base permisos',
    'resultado'   => 'Rechazado',
]), $fase->id);
$dict = $fase->observaciones()->orderByDesc('id')->first();

Auth::loginUsingId(1); // Produccion
$resp = (new ObservacionCalidadController)->edit($dict->id);
check(errorDe($resp) !== '', 'produccion no puede abrir la edicion');
check(str_contains(errorDe($resp), 'Solo el Jefe de Calidad'), 'mensaje claro al intentar editar');

(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'HACKEADO',
    'resultado'   => 'Aprobado',
]), $dict->id);
$dict->refresh();
check($dict->descripcion === 'base permisos', 'produccion no logro modificar el dictamen');

$resp = (new ObservacionCalidadController)->destroy($dict->id);
check(errorDe($resp) !== '', 'produccion no puede eliminar');
check(ObservacionCalidad::whereKey($dict->id)->exists(), 'el dictamen sigue existiendo tras el intento de produccion');

echo "== RF-33: se notifica en rechazo y no en aprobado ==\n";
Auth::loginUsingId(2);

// La fase quedo en Rechazada por el dictamen de la seccion de permisos;
// store solo admite dictamenes en En Proceso / En Reproceso.
$fase->observaciones()->delete();
FasePieza::whereKey($fase->id)->update(['estatus_fase_id' => $enProceso]);
$fase->refresh();

Http::fake(['*' => Http::response(['ok' => true], 200)]);
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'nuevo reproceso',
    'resultado'   => 'Enviado a Reproceso',
]), $fase->id);
Http::assertSentCount(1);
check(true, 'el reproceso dispara la alerta de WhatsApp (1 request a n8n)');

$otro = $fase->observaciones()->orderByDesc('id')->first();

Http::fake(['*' => Http::response(['ok' => true], 200)]);
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'ahora aprobado',
    'resultado'   => 'Aprobado',
]), $otro->id);
Http::assertNothingSent();
check(true, 'un dictamen aprobado no dispara alerta (0 requests)');

echo "== auditoria: editar sin cambios no re-marca una fecha nueva ==\n";
$otro->refresh();
$fechaAntes = $otro->fechaEdicion()?->toDateTimeString();
check($otro->fueEditado() === true, 'el update previo si marco el dictamen');

// Mismo contenido exacto: no debe actualizar editado_at
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'ahora aprobado',
    'resultado'   => 'Aprobado',
]), $otro->id);
$otro->refresh();
check(
    $otro->fechaEdicion()?->toDateTimeString() === $fechaAntes,
    'update sin cambios: editado_at NO cambia'
);

echo "== auditoria: el partial muestra el badge Editado a ambos roles ==\n";
$fase->load(['observaciones.inspector', 'observaciones.editadoPor', 'observaciones.evidencias', 'fase', 'operario']);
$htmlEditado = View::make('piezas.partials.observaciones', ['hist' => $fase])->render();

check(str_contains($htmlEditado, 'Editado'), 'el partial renderiza el badge "Editado"');

// Produccion tambien debe verlo (solo Calidad edita, ambos ven)
Auth::loginUsingId(1);
$fase->load(['observaciones.inspector', 'observaciones.editadoPor', 'observaciones.evidencias', 'fase', 'operario']);
$htmlProd = View::make('piezas.partials.observaciones', ['hist' => $fase])->render();
check(str_contains($htmlProd, 'Editado'), 'Produccion tambien ve el badge "Editado"');
check(! str_contains($htmlProd, 'value="DELETE"'), 'Produccion sigue sin poder eliminar');

echo "== orden: los dictamenes mas recientes aparecen primero ==\n";
// Aseguramos al menos 3 dictamenes para validar el orden real (no solo el caso vacio).
$fase->observaciones()->delete();
FasePieza::whereKey($fase->id)->update(['estatus_fase_id' => $enProceso]);
$fase->refresh();

// created_at distintos para que el orden sea determinista por fecha, no por id.
$base = now()->subMinutes(10);
$creados = [];
foreach (['orden A', 'orden B', 'orden C'] as $i => $txt) {
    $creados[$txt] = ObservacionCalidad::create([
        'fase_pieza_id' => $fase->id,
        'usuario_id'    => 2,
        'descripcion'   => $txt,
        'resultado'     => 'Aprobado',
        'created_at'    => $base->copy()->addMinutes($i),
    ]);
}

$fase->refresh();
$ids = $fase->observaciones->pluck('id')->all();
check(count($ids) >= 3, 'hay varios dictamenes para validar el orden');
check($ids[0] === $creados['orden C']->id, 'el mas reciente (created_at) aparece primero');
check(
    $ids === collect($ids)->sortDesc()->values()->all(),
    'sin ediciones, la relacion viene en orden desc por fecha de creacion'
);

echo "== orden: un dictamen EDITADO sube al tope ==\n";
// Editamos el mas antiguo ("orden A"): debe saltar al primer lugar pese a su id menor.
$creados['orden A']->update(['editado' => true, 'editado_at' => now()->addMinutes(5), 'editado_por' => 2]);

$fase->refresh();
$idsEdit = $fase->observaciones->pluck('id')->all();
check($idsEdit[0] === $creados['orden A']->id, 'el dictamen recien editado sube al tope');
check($idsEdit[1] === $creados['orden C']->id, 'el segundo es el de created_at mas reciente');
check($idsEdit[2] === $creados['orden B']->id, 'el ultimo es el mas antiguo sin editar');

echo "== inmutabilidad: fase finalizada (Completada) bloquea editar/eliminar ==\n";
$completada = CatEstatusFase::where('nombre', 'Completada')->value('id');
$rechazada  = CatEstatusFase::where('nombre', 'Rechazada')->value('id');

// Reutilizamos la fase base (la tabla exige UNIQUE pieza_id+fase_id):
// la marcamos Completada y le dejamos un dictamen Aprobado vigente.
$fase->observaciones()->delete();
FasePieza::whereKey($fase->id)->update(['estatus_fase_id' => $completada]);
$fase->refresh();
$dictCerrado = ObservacionCalidad::create([
    'fase_pieza_id' => $fase->id,
    'usuario_id'    => 2,
    'descripcion'   => 'dictamen historico',
    'resultado'     => 'Aprobado',
]);
$fase->refresh();
$faseCerrada = $fase;

check($faseCerrada->terminada() === true, 'la fase esta Completada (terminada)');
check($faseCerrada->dictamenesInmutables() === true, 'la fase cerrada marca sus dictamenes como inmutables');

// 1) edit: no debe renderizar el formulario, redirige con error.
Auth::loginUsingId(2); // Calidad
$respEdit = (new ObservacionCalidadController)->edit($dictCerrado->id);
check(! method_exists($respEdit, 'render'), 'edit en fase cerrada NO devuelve vista');
check(str_contains(errorDe($respEdit), 'solo lectura'), 'edit en fase cerrada redirige con mensaje de solo lectura');

// 2) update: no debe modificar el dictamen.
$respUpd = (new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'intento de cambio',
    'resultado'   => 'Rechazado',
]), $dictCerrado->id);
$dictCerrado->refresh();
check($dictCerrado->descripcion === 'dictamen historico', 'update en fase cerrada NO cambia la descripcion');
check($dictCerrado->resultado === 'Aprobado', 'update en fase cerrada NO cambia el resultado');
check($dictCerrado->fueEditado() === false, 'update en fase cerrada NO marca el dictamen como editado');
check(str_contains(errorDe($respUpd), 'solo lectura'), 'update en fase cerrada responde con mensaje de solo lectura');

// 3) destroy: no debe borrar el dictamen.
$respDel = (new ObservacionCalidadController)->destroy($dictCerrado->id);
check(ObservacionCalidad::whereKey($dictCerrado->id)->exists(), 'destroy en fase cerrada NO borra el dictamen');
check(str_contains(errorDe($respDel), 'solo lectura'), 'destroy en fase cerrada responde con mensaje de solo lectura');

// 4) La fase NO cambia de estado tras los intentos.
$faseCerrada->refresh();
check($faseCerrada->estatusFase?->nombre === 'Completada', 'la fase cerrada sigue Completada');

// 5) La vista oculta los botones y muestra la nota de solo lectura.
$faseCerrada->load(['observaciones.inspector', 'observaciones.editadoPor', 'observaciones.evidencias', 'fase', 'operario']);
$htmlCerrada = View::make('piezas.partials.observaciones', ['hist' => $faseCerrada])->render();
check(! str_contains($htmlCerrada, 'value="DELETE"'), 'la vista cerrada NO muestra el boton Eliminar');
check(! str_contains($htmlCerrada, route('calidad.edit', $dictCerrado->id)), 'la vista cerrada NO muestra el enlace Editar');
check(str_contains($htmlCerrada, 'solo lectura'), 'la vista cerrada muestra la nota de solo lectura');

echo "== inmutabilidad: una fase NO cerrada sigue siendo editable ==\n";
// Contraprueba: la misma logica NO debe bloquear una fase Rechazada (no terminada).
$fase->observaciones()->delete();
FasePieza::whereKey($fase->id)->update(['estatus_fase_id' => $rechazada]);
$fase->refresh();
$dictRechazado = ObservacionCalidad::create([
    'fase_pieza_id' => $fase->id,
    'usuario_id'    => 2,
    'descripcion'   => 'dictamen editable',
    'resultado'     => 'Aprobado',
]);
$fase->refresh();
$faseRechazada = $fase;

check($faseRechazada->dictamenesInmutables() === false, 'una fase Rechazada NO es inmutable');

Auth::loginUsingId(2);
$respEditAbierta = (new ObservacionCalidadController)->edit($dictRechazado->id);
check(method_exists($respEditAbierta, 'render'), 'edit en fase no cerrada SI devuelve el formulario');
check(str_contains($respEditAbierta->render(), 'name="descripcion"'), 'el formulario editable trae el campo descripcion');

$faseRechazada->load(['observaciones.inspector', 'observaciones.editadoPor', 'observaciones.evidencias', 'fase', 'operario']);
$htmlAbierta = View::make('piezas.partials.observaciones', ['hist' => $faseRechazada])->render();
check(str_contains($htmlAbierta, route('calidad.edit', $dictRechazado->id)), 'la vista no cerrada SI muestra el enlace Editar');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

<?php

use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\CatEstatusFase;
use App\Models\FasePieza;
use App\Models\ObservacionCalidad;
use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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

// Reinicia el stub y el contador de peticiones HTTP. Permite medir cuántas
// alertas dispara cada acción de forma aislada.
function reiniciarHttp(): void
{
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
}

function envios(): int
{
    return Http::recorded()->count();
}

config([
    'services.n8n.notify_phone' => '+52 1 55 1234 5678',
    'services.n8n.webhook_url'  => 'https://n8n.test/webhook',
]);
reiniciarHttp();

echo "== Datos base ==\n";
Auth::loginUsingId(1); // Producción: crea obra/pieza/fase
(new ObraController)->store(xpost([
    'codigo_obra'      => 'TEST-WA-001',
    'nombre_obra'      => 'Obra WhatsApp Test',
    'cliente'          => 'Cliente WhatsApp',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));
$obra = Obra::where('codigo_obra', 'TEST-WA-001')->firstOrFail();

(new PiezaController)->store(xpost([
    'clave_marca_pieza'  => 'WA1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 80,
    'cantidad'           => 1,
]), $obra->id);
$pieza = Pieza::where('clave_marca_pieza', 'WA1')->where('obra_id', $obra->id)->firstOrFail();

$enProceso = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$fase = FasePieza::create([
    'pieza_id'           => $pieza->id,
    'fase_id'            => 1,
    'operario_id'        => 1,
    'usuario_captura_id' => 1,
    'estatus_fase_id'    => $enProceso,
    'fecha_inicio'       => now(),
]);
check($fase->exists, 'fase base creada en En Proceso');

Auth::loginUsingId(2); // Calidad

echo "== store Rechazado: 1 alerta con payload completo ==\n";
reiniciarHttp();
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'rechazo para alerta',
    'resultado'   => 'Rechazado',
]), $fase->id);

$dictRechazo = ObservacionCalidad::where('fase_pieza_id', $fase->id)->orderByDesc('id')->first();
check(envios() === 1, 'store Rechazado dispara exactamente 1 alerta');

$request  = Http::recorded()->last()[0];
$payload  = $request->data();
check($request->url() === 'https://n8n.test/webhook', 'la alerta va al webhook configurado');
check(($payload['resultado'] ?? null) === 'Rechazado', 'el payload lleva el resultado Rechazado');
check(($payload['telefono_destino'] ?? null) === '5215512345678', 'el teléfono se normaliza a 521 + 10 dígitos');
check(($payload['obra'] ?? null) === 'Obra WhatsApp Test', 'el payload lleva el nombre de la obra');
check(($payload['marca'] ?? null) === 'WA1', 'el payload lleva la marca de la pieza');
check(isset($payload['fase']) && $payload['fase'] !== '', 'el payload lleva la fase');
check(
    preg_match('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#', (string) ($payload['fecha'] ?? '')) === 1,
    'la fecha del payload tiene formato d/m/Y H:i'
);
check(! array_key_exists('password_hash', $payload), 'el payload no expone password_hash');
check($dictRechazo !== null, 'el dictamen Rechazado quedó registrado');

echo "== store Aprobado: no genera alerta (RF-33) ==\n";
FasePieza::whereKey($fase->id)->update(['estatus_fase_id' => $enProceso]);
$fase->refresh();
reiniciarHttp();
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'aprobado sin alerta',
    'resultado'   => 'Aprobado',
]), $fase->id);
check(envios() === 0, 'store Aprobado no dispara alerta');

echo "== update sin cambio de resultado: no duplica la alerta ==\n";
FasePieza::whereKey($fase->id)->update(['estatus_fase_id' => $enProceso]);
$fase->refresh();
reiniciarHttp();
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'reproceso base',
    'resultado'   => 'Enviado a Reproceso',
]), $fase->id);
check(envios() === 1, 'store Enviado a Reproceso dispara 1 alerta');
$dictReproceso = ObservacionCalidad::where('fase_pieza_id', $fase->id)->orderByDesc('id')->first();

reiniciarHttp();
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'reproceso con descripción editada',
    'resultado'   => 'Enviado a Reproceso',
]), $dictReproceso->id);
check(envios() === 0, 'editar solo la descripción no reenvía la alerta');

echo "== update de Aprobado a Reproceso: 1 alerta ==\n";
reiniciarHttp();
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'cambia a aprobado',
    'resultado'   => 'Aprobado',
]), $dictReproceso->id);
check(envios() === 0, 'cambiar de Reproceso a Aprobado no alerta');

reiniciarHttp();
(new ObservacionCalidadController)->update(xpost([
    'descripcion' => 'vuelve a reproceso',
    'resultado'   => 'Enviado a Reproceso',
]), $dictReproceso->id);
check(envios() === 1, 'cambiar de Aprobado a Reproceso dispara 1 alerta');

echo "== destroy: no reenvía el dictamen vigente ==\n";
// Se crea un Aprobado encima del reproceso: al borrarlo, el código anterior
// reenviaba el reproceso histórico con la fecha de hoy (alerta fantasma).
reiniciarHttp();
(new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'aprobado que se va a eliminar',
    'resultado'   => 'Aprobado',
]), $fase->id);
$dictAEliminar = ObservacionCalidad::where('fase_pieza_id', $fase->id)->orderByDesc('id')->first();
check(envios() === 0, 'el dictamen Aprobado previo a la eliminación no alerta');

reiniciarHttp();
$respDestroy = (new ObservacionCalidadController)->destroy($dictAEliminar->id);
check(envios() === 0, 'eliminar un dictamen NO dispara ninguna alerta');
check(! ObservacionCalidad::whereKey($dictAEliminar->id)->exists(), 'el dictamen se eliminó de la BD');
check(str_contains(successDe($respDestroy), 'eliminado'), 'destroy responde con mensaje de éxito');

$fase->refresh();
check(
    $fase->estatusFase?->nombre === 'En Reproceso',
    'la fase vuelve al estado del último dictamen vigente'
);

echo "== teléfono inválido: se omite sin errores ==\n";
config(['services.n8n.notify_phone' => '521XXXXXXXXXX']);
reiniciarHttp();
$respInvalido = (new ObservacionCalidadController)->store(xpost([
    'descripcion' => 'con teléfono inválido',
    'resultado'   => 'Rechazado',
]), $fase->id);
check(envios() === 0, 'un teléfono placeholder no llama a n8n');
check($respInvalido->getSession()?->get('error') === null, 'la operación no falla por teléfono inválido');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}

<?php

use App\Http\Controllers\WhatsAppController;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

function fakeEvolution($respuesta): void
{
    // Cada escenario necesita una factory limpia: los stubs se acumulan.
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*' => $respuesta]);
}

function estadoDe($respuesta): array
{
    return $respuesta instanceof JsonResponse ? (array) $respuesta->getData(true) : [];
}

// El bootstrap deja un Http::fake() global que responde vacío y se antepone
// a cualquier stub nuevo. Se sustituye la factory para poder simular Evolution.
Http::swap(new \Illuminate\Http\Client\Factory);

config([
    'services.evolution.url'      => 'https://evolution.test',
    'services.evolution.api_key'  => 'clave-prueba-no-real',
    'services.evolution.instance' => 'famep-wa',
    'services.evolution.timeout'  => 10,
]);

$controlador = new WhatsAppController;
$calidad     = User::where('rol_id', 2)->firstOrFail();
$produccion  = User::where('rol_id', 1)->firstOrFail();
$cliente     = User::where('rol_id', 3)->first();

echo "== Rutas ==\n";
check(Route::has('whatsapp.qr'), 'ruta whatsapp.qr registrada');
check(Route::has('whatsapp.qr.estado'), 'ruta whatsapp.qr.estado registrada');

echo "== Pagina: solo Calidad ==\n";
Auth::loginUsingId($calidad->id);
$vista = $controlador->index();
check($vista instanceof \Illuminate\View\View, 'Calidad puede abrir la pagina');
$html = $vista->render();
check(str_contains($html, 'Vincular WhatsApp'), 'la vista muestra el titulo en espanol');
check(str_contains($html, route('whatsapp.qr.estado')), 'la vista consulta el endpoint configurado');
check(str_contains($html, 'Escanee el código desde su celular'), 'la vista explica como vincular');
check(str_contains($html, 'aria-live="polite"'), 'la vista expone el estado a lectores de pantalla');
check(! str_contains($html, 'clave-prueba-no-real'), 'la vista no expone la API key');

Auth::loginUsingId($produccion->id);
$redireccion = $controlador->index();
check($redireccion instanceof RedirectResponse, 'Produccion es redirigido de la pagina');
check(str_contains($redireccion->getTargetUrl(), 'dashboard'), 'Produccion vuelve al dashboard');
$jsonProduccion = $controlador->estado();
check($jsonProduccion->getStatusCode() === 403, 'el endpoint JSON rechaza a Produccion con 403');

if ($cliente !== null) {
    Auth::loginUsingId($cliente->id);
    check($controlador->estado()->getStatusCode() === 403, 'el endpoint JSON rechaza al cliente con 403');
}

echo "== Menu lateral ==\n";
Auth::loginUsingId($calidad->id);
$menuCalidad = view('components.sidebar-menu')->render();
check(str_contains($menuCalidad, route('whatsapp.qr')), 'Calidad ve el enlace WhatsApp en el menu');

Auth::loginUsingId($produccion->id);
$menuProduccion = view('components.sidebar-menu')->render();
check(! str_contains($menuProduccion, route('whatsapp.qr')), 'Produccion no ve el enlace WhatsApp');

echo "== Evolution devuelve QR ==\n";
Auth::loginUsingId($calidad->id);
fakeEvolution(Http::response(['base64' => 'iVBORw0KGgoAAAANSUhEUg=='], 200));
$respuesta = $controlador->estado();
$datos     = estadoDe($respuesta);
check(($datos['estado'] ?? null) === 'qr', 'una respuesta con base64 se interpreta como QR');
check(str_starts_with((string) ($datos['imagen'] ?? ''), 'data:image/png;base64,'), 'el QR se entrega como data URI');
check(($datos['instancia'] ?? null) === 'famep-wa', 'la respuesta indica la instancia');
check(! str_contains((string) $respuesta->getContent(), 'clave-prueba-no-real'), 'el JSON no filtra la API key');

echo "== Evolution ya vinculada ==\n";
fakeEvolution(Http::response(['instance' => ['state' => 'open']], 200));
$datos = estadoDe($controlador->estado());
check(($datos['estado'] ?? null) === 'vinculada', 'state open se interpreta como vinculada');
check(! array_key_exists('imagen', $datos), 'una instancia vinculada no devuelve QR');

echo "== Evolution falla ==\n";
fakeEvolution(Http::response(['message' => 'boom'], 500));
$respuesta = $controlador->estado();
$datos     = estadoDe($respuesta);
check(($datos['estado'] ?? null) === 'error', 'un 5xx se convierte en estado error');
check(str_contains((string) ($datos['mensaje'] ?? ''), 'servicio de WhatsApp'), 'el mensaje de error esta en espanol');
check(! str_contains((string) $respuesta->getContent(), 'boom'), 'el cuerpo de Evolution no se expone al navegador');

echo "== Evolution inaccesible ==\n";
fakeEvolution(function () {
    throw new ConnectionException('timeout');
});
$datos = estadoDe($controlador->estado());
check(($datos['estado'] ?? null) === 'error', 'una excepcion de conexion se convierte en error');
check(str_contains((string) ($datos['mensaje'] ?? ''), 'No se pudo contactar'), 'el error de conexion tiene mensaje claro');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}

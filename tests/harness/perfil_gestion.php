<?php

use App\Http\Controllers\PerfilController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

function successDe($resp)
{
    return (string) ($resp->getSession()?->get('success') ?? '');
}

// Ejecuta el cambio de contrasena con una sesion real, como el formulario.
// Devuelve [respuesta, errores]. Los ValidationException se capturan aqui.
function intentarCambio(array $data)
{
    $req = Request::create('/perfil/password', 'PUT', $data);

    if (! app()->bound('session')) {
        app()->register(\Illuminate\Session\SessionServiceProvider::class);
    }

    $session = app('session')->driver();
    $session->start();
    $req->setLaravelSession($session);

    try {
        $resp = (new PerfilController)->updatePassword($req);

        return [$resp, []];
    } catch (ValidationException $e) {
        return [null, $e->errors()];
    }
}

echo "== Rutas del perfil ==\n";
check(Route::has('perfil.edit'), 'ruta perfil.edit registrada');
check(Route::has('perfil.password'), 'ruta perfil.password registrada');

echo "== Ver perfil (Produccion) ==\n";
$produccion = User::where('rol_id', 1)->firstOrFail();
Auth::loginUsingId($produccion->id);

$vista = (new PerfilController)->edit();
check($vista instanceof \Illuminate\View\View, 'edit devuelve la vista del perfil');
$html = $vista->render();
check(str_contains($html, $produccion->email), 'la vista muestra el correo del usuario');
check(str_contains($html, $produccion->nombre), 'la vista muestra el nombre del usuario');
check(str_contains($html, 'name="password_actual"'), 'el formulario pide la contrasena actual');
check(str_contains($html, 'name="password_confirmation"'), 'el formulario pide la confirmacion');

echo "== Cliente NO puede entrar al perfil ==\n";
$cliente = User::where('rol_id', 3)->first();
if ($cliente !== null) {
    Auth::loginUsingId($cliente->id);
    $respCli = (new PerfilController)->edit();
    check(! method_exists($respCli, 'render'), 'el cliente no obtiene la vista del perfil');
    check(str_contains((string) $respCli->getTargetUrl(), 'consulta'), 'el cliente es enviado al portal de consulta');
} else {
    check(true, 'no hay cliente en la base de prueba (se omite)');
    check(true, 'no hay cliente en la base de prueba (se omite)');
}

echo "== Preparacion: clave conocida para Produccion ==\n";
$produccion = User::where('rol_id', 1)->firstOrFail();
$produccion->update(['password_hash' => Hash::make('claveActual123')]);
$produccion->refresh();
check(Hash::check('claveActual123', $produccion->password_hash), 'la clave base quedo establecida');

echo "== Rechazos de validacion ==\n";
Auth::loginUsingId($produccion->id);

// 1) Contrasena actual incorrecta
[$r, $errs] = intentarCambio([
    'password_actual'       => 'claveEquivocada',
    'password'              => 'nuevaClaveSegura123',
    'password_confirmation' => 'nuevaClaveSegura123',
]);
$produccion->refresh();
check(isset($errs['password_actual']), 'rechaza si la contrasena actual es incorrecta');
check(Hash::check('claveActual123', $produccion->password_hash), 'el hash NO cambia tras un intento fallido');

// 2) Nueva contrasena demasiado corta
[$r, $errs] = intentarCambio([
    'password_actual'       => 'claveActual123',
    'password'              => 'corta',
    'password_confirmation' => 'corta',
]);
check(isset($errs['password']), 'rechaza una contrasena nueva de menos de 8 caracteres');

// 3) Confirmacion distinta
[$r, $errs] = intentarCambio([
    'password_actual'       => 'claveActual123',
    'password'              => 'nuevaClaveSegura123',
    'password_confirmation' => 'otraDistinta456',
]);
check(isset($errs['password']), 'rechaza si la confirmacion no coincide');

// 4) Nueva igual a la actual
[$r, $errs] = intentarCambio([
    'password_actual'       => 'claveActual123',
    'password'              => 'claveActual123',
    'password_confirmation' => 'claveActual123',
]);
check(isset($errs['password']), 'rechaza si la nueva es igual a la actual');

echo "== Cambio exitoso (Produccion) ==\n";
$hashAntes = $produccion->password_hash;
[$respOk, $errs] = intentarCambio([
    'password_actual'       => 'claveActual123',
    'password'              => 'nuevaClaveSegura123',
    'password_confirmation' => 'nuevaClaveSegura123',
]);
$produccion->refresh();
check(Hash::check('nuevaClaveSegura123', $produccion->password_hash), 'la contrasena nueva queda guardada');
check($produccion->password_hash !== $hashAntes, 'el hash cambio respecto al anterior');
check(str_contains(successDe($respOk), 'actualizada'), 'responde con mensaje de exito');

echo "== La contrasena nueva permite iniciar sesion ==\n";
Auth::logout();
$cred = ['email' => $produccion->email, 'password' => 'nuevaClaveSegura123', 'activo' => true];
check(Auth::attempt($cred) === true, 'Auth::attempt valida con la contrasena nueva');
check(Auth::attempt(['email' => $produccion->email, 'password' => 'claveActual123', 'activo' => true]) === false, 'la contrasena anterior ya NO sirve');

echo "== Nombre, correo y rol NO se alteran ==\n";
$produccion->refresh();
check($produccion->nombre === 'Carlos Mendoza', 'el nombre se conserva');
check($produccion->email === 'produccion@famep.com', 'el correo se conserva');
check((int) $produccion->rol_id === 1, 'el rol se conserva');

echo "== Calidad tambien puede cambiar su contrasena ==\n";
$calidad = User::where('rol_id', 2)->firstOrFail();
$calidad->update(['password_hash' => Hash::make('calidadActual123')]);
Auth::loginUsingId($calidad->id);

[$respCal, $errsCal] = intentarCambio([
    'password_actual'       => 'calidadActual123',
    'password'              => 'calidadNueva12345',
    'password_confirmation' => 'calidadNueva12345',
]);
$calidad->refresh();
check(Hash::check('calidadNueva12345', $calidad->password_hash), 'Calidad tambien puede cambiar su contrasena');
check(str_contains(successDe($respCal), 'actualizada'), 'Calidad recibe mensaje de exito');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

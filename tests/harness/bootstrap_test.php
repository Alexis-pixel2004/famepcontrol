<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

$raiz = dirname(__DIR__, 2);

require $raiz . '/vendor/autoload.php';

$app = require $raiz . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Red de seguridad del arnés: ninguna suite debe disparar alertas reales a
// n8n/WhatsApp. Las suites que necesitan contar peticiones vuelven a llamar
// a Http::fake() con su propio stub.
Http::fake();

// Artefacto del harness: las vistas renderizadas fuera del kernel HTTP
// no comparten $errors (en producción lo hace ShareErrorsFromSession).
View::share('errors', new ViewErrorBag());

// Operario aislado para la suite. Sin fases configuradas es comodín
// (Operario::puedeHacerFase devuelve true), así las pruebas no dependen de
// los operarios ni de las asignaciones reales de la base de datos.
if (! function_exists('operarioDePrueba')) {
    function operarioDePrueba(string $prefijo = 'TST', array $fases = []): \App\Models\Operario
    {
        $operario = \App\Models\Operario::create([
            'clave_empleado' => 'T-' . $prefijo . '-' . strtoupper(\Illuminate\Support\Str::random(4)),
            'nombre'         => 'Operario',
            'apellido'       => 'Prueba ' . $prefijo,
            'activo'         => true,
        ]);

        if ($fases !== []) {
            $operario->fasesHabilitadas()->sync($fases);
        }

        return $operario;
    }
}

$script = $argv[1] ?? null;

if ($script === null) {
    fwrite(STDERR, "Uso: php tests/harness/bootstrap_test.php <suite.php>\n");
    exit(1);
}

// Resuelve rutas relativas al raiz del proyecto (funciona en Windows y Linux)
if (! str_starts_with($script, DIRECTORY_SEPARATOR) && ! str_starts_with($script, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $script)) {
    $script = $raiz . '/' . str_replace('\\', '/', $script);
}

if (! is_file($script)) {
    fwrite(STDERR, "Suite no encontrada: $script\n");
    exit(1);
}

DB::beginTransaction();

$estado = 0;

try {
    require $script;
} catch (Throwable $e) {
    $estado = 1;
    fwrite(STDERR, "\n[ERROR] " . $e->getMessage() . "\n        " . $e->getFile() . ':' . $e->getLine() . "\n");
}

if (DB::transactionLevel() > 0) {
    DB::rollBack();
    echo "[rollback ok: la base de datos queda intacta]\n";
}

exit($estado);
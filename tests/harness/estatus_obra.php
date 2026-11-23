<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FasePiezaController;
use App\Http\Controllers\ObservacionCalidadController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\PiezaController;
use App\Models\CatEstatusFase;
use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

echo "== RF-01: estatus de obra derivado automaticamente ==\n";

// Linea base de KPIs: se toma antes de crear datos de prueba para que las
// verificaciones no dependan de cuantas obras reales existan en la base.
$kpiBase = DB::table('obras')
    ->selectRaw('estatus, COUNT(*) as total')
    ->groupBy('estatus')
    ->pluck('total', 'estatus')
    ->map(fn ($total) => (int) $total)
    ->all();

$estatusRealPrevio = Obra::where('codigo_obra', 'OB-2026-001')->value('estatus');

// 1) Obra nueva (sin piezas) => Pendiente
(new ObraController)->store(Request::create('/obras', 'POST', [
    'codigo_obra'      => 'TEST-EST-001',
    'nombre_obra'      => 'Obra Prueba Estatus',
    'cliente'          => 'Cliente de Prueba',
    'fecha_inicio'     => '2026-10-01',
    'fecha_compromiso' => '2026-12-31',
]));

$obra = Obra::where('codigo_obra', 'TEST-EST-001')->firstOrFail();
check($obra->estatus === 'Pendiente', '1. obra nueva => Pendiente (obtenido: ' . $obra->estatus . ')');

// 2) Vistas: chips por estatus (Pendiente ambar, Activa verde)
Auth::loginUsingId(1);

$htmlIndex = (new ObraController)->index()->render();
check(str_contains($htmlIndex, 'bg-amber-100 text-amber-700'), '2a. index muestra chip ambar para obra Pendiente');
check(str_contains($htmlIndex, 'bg-green-100 text-green-700'), '2b. index muestra chip verde para OB-2026-001 (Activa)');

$htmlShow = (new ObraController)->show($obra->id)->render();
check(str_contains($htmlShow, 'bg-amber-100 text-amber-700'), '2c. detalle muestra chip ambar para obra Pendiente');

// 3) Alta de pieza sin fases => la obra sigue Pendiente
(new PiezaController)->store(Request::create('/x', 'POST', [
    'clave_marca_pieza'  => 'TS1',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 150,
    'cantidad'           => 2,
]), $obra->id);

$obra->refresh();
check($obra->estatus === 'Pendiente', '3. pieza sin avance => obra Pendiente (obtenido: ' . $obra->estatus . ')');

$pieza = Pieza::where('clave_marca_pieza', 'TS1')->firstOrFail();

$enProcesoId  = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
$completadaId = CatEstatusFase::where('nombre', 'Completada')->value('id');

// Operario propio de la suite para no depender de los operarios reales.
$operarioPrueba = operarioDePrueba('ES');
$GLOBALS['operarioPruebaId'] = $operarioPrueba->id;

function iniciar($piezaId, $faseId, $estatusId, $operarioId = null)
{
    $operarioId ??= $GLOBALS['operarioPruebaId'];
    Auth::loginUsingId(1);
    (new FasePiezaController)->store(Request::create('/x', 'POST', [
        'fase_id'         => $faseId,
        'operario_id'     => $operarioId,
        'estatus_fase_id' => $estatusId,
    ]), $piezaId);
}

function dictaminar($piezaId, $faseId, $resultado, $descripcion = 'Dictamen de prueba')
{
    $fasePiezaId = DB::table('fases_pieza')
        ->where('pieza_id', $piezaId)
        ->where('fase_id', $faseId)
        ->value('id');

    Auth::loginUsingId(2);
    (new ObservacionCalidadController)->store(Request::create('/x', 'POST', [
        'descripcion' => $descripcion,
        'resultado'   => $resultado,
    ]), $fasePiezaId);
}

// 4) Iniciar primera fase => pieza En Proceso y obra Activa
iniciar($pieza->id, 1, $enProcesoId);

$pieza->refresh();
$obra->refresh();
check($pieza->estatus?->nombre === 'En Proceso', '4a. fase iniciada => pieza En Proceso (obtenido: ' . ($pieza->estatus?->nombre ?? 'null') . ')');
check($obra->estatus === 'Activa', '4b. obra con avance => Activa (obtenido: ' . $obra->estatus . ')');

// 5) Calidad aprueba => la pieza sigue En Proceso (falta el cierre) y la obra Activa
dictaminar($pieza->id, 1, 'Aprobado');

$pieza->refresh();
$obra->refresh();
check($pieza->estatus?->nombre === 'En Proceso', '5a. aprobada sin cerrar => pieza En Proceso (obtenido: ' . ($pieza->estatus?->nombre ?? 'null') . ')');
check($obra->estatus === 'Activa', '5b. tras aprobacion => obra Activa (obtenido: ' . $obra->estatus . ')');

// 6) Cerrar primera fase => aun hay fases pendientes, obra Activa
iniciar($pieza->id, 1, $completadaId);

$pieza->refresh();
$obra->refresh();
check($pieza->estatus?->nombre === 'En Proceso', '6a. 1 de 3 fases => pieza En Proceso (obtenido: ' . ($pieza->estatus?->nombre ?? 'null') . ')');
check($obra->estatus === 'Activa', '6b. obra Activa (obtenido: ' . $obra->estatus . ')');

// 7) Completar Soldadura y Pintura => pieza Aprobada y obra Finalizada
iniciar($pieza->id, 2, $enProcesoId);
dictaminar($pieza->id, 2, 'Aprobado');
iniciar($pieza->id, 2, $completadaId);

iniciar($pieza->id, 3, $enProcesoId);
dictaminar($pieza->id, 3, 'Aprobado');
iniciar($pieza->id, 3, $completadaId);

$pieza->refresh();
$obra->refresh();
check($pieza->estatus?->nombre === 'Aprobada', '7a. 3 de 3 fases cerradas => pieza Aprobada (obtenido: ' . ($pieza->estatus?->nombre ?? 'null') . ')');
check($obra->estatus === 'Finalizada', '7b. toda la obra aprobada => Finalizada (obtenido: ' . $obra->estatus . ')');

// 8) Alta de una marca nueva en obra Finalizada => vuelve a Activa
(new PiezaController)->store(Request::create('/x', 'POST', [
    'clave_marca_pieza'  => 'TS2',
    'tipo_estructura_id' => 1,
    'peso_kg'            => 200,
    'cantidad'           => 1,
]), $obra->id);

$obra->refresh();
check($obra->estatus === 'Activa', '8. obra Finalizada + pieza nueva => Activa (obtenido: ' . $obra->estatus . ')');

$pieza2 = Pieza::where('clave_marca_pieza', 'TS2')->firstOrFail();
check($pieza2->estatus?->nombre === 'Pendiente', '8b. pieza nueva => Pendiente (obtenido: ' . ($pieza2->estatus?->nombre ?? 'null') . ')');

// 9) Rechazado => pieza Detenida y obra Activa (no Finalizada)
iniciar($pieza2->id, 1, $enProcesoId);
dictaminar($pieza2->id, 1, 'Rechazado');

$pieza2->refresh();
$obra->refresh();
check($pieza2->estatus?->nombre === 'Detenida', '9a. dictamen Rechazado => pieza Detenida (obtenido: ' . ($pieza2->estatus?->nombre ?? 'null') . ')');
check($obra->estatus === 'Activa', '9b. con detenida => obra Activa (obtenido: ' . $obra->estatus . ')');

// 10) Obra real OB-2026-001 => Activa (TB2 no aprobada) y KPIs del dashboard mueven
$obraReal = Obra::where('codigo_obra', 'OB-2026-001')->firstOrFail();
$obraReal->recalcularEstatus();
check($obraReal->estatus === 'Activa', '10. OB-2026-001 derivada => Activa (obtenido: ' . $obraReal->estatus . ')');

// La obra de prueba termina Activa. Si la obra real no estaba Activa, su
// recalculo tambien suma una Activa y resta una de su estatus previo.
$esperadoKpi = $kpiBase;
$esperadoKpi['Activa'] = ($esperadoKpi['Activa'] ?? 0) + 1;

if ($estatusRealPrevio !== null && $estatusRealPrevio !== 'Activa') {
    $esperadoKpi[$estatusRealPrevio] = max(0, ($esperadoKpi[$estatusRealPrevio] ?? 0) - 1);
    $esperadoKpi['Activa']++;
}

check(Obra::where('estatus', 'Activa')->count() === ($esperadoKpi['Activa'] ?? 0), '10b. KPI Obras Activas = ' . ($esperadoKpi['Activa'] ?? 0) . ' (base ' . ($kpiBase['Activa'] ?? 0) . ' + prueba)');
check(Obra::where('estatus', 'Finalizada')->count() === ($esperadoKpi['Finalizada'] ?? 0), '10c. KPI Obras Finalizadas = ' . ($esperadoKpi['Finalizada'] ?? 0));
check(Obra::where('estatus', 'Pendiente')->count() === ($esperadoKpi['Pendiente'] ?? 0), '10d. KPI Obras Pendientes = ' . ($esperadoKpi['Pendiente'] ?? 0));

// 11) Dashboard sigue renderizando (RF-03 intacto)
$htmlDashboard = (new DashboardController)->index()->render();
check(str_contains($htmlDashboard, 'Obras Activas'), '11a. dashboard muestra KPI Obras Activas');
check(str_contains($htmlDashboard, 'Cumplimiento General del Taller'), '11b. panel RF-03 presente');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new RuntimeException("{$GLOBALS['fail']} verificaciones fallaron.");
}
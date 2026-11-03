<?php

use App\Http\Controllers\ReporteController;
use App\Models\CatFase;
use App\Models\CatEstatusFase;
use App\Models\FasePieza;
use App\Models\Obra;
use App\Models\Pieza;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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

function xget(array $query = [])
{
    return Request::create('/reportes', 'GET', $query);
}

echo "== Rutas de reportes ==\n";
check(Route::has('reportes.index'), 'ruta reportes.index registrada');
check(Route::has('reportes.avance-obra'), 'ruta reportes.avance-obra registrada');

echo "== Indice de reportes (Produccion) ==\n";
$produccion = User::where('rol_id', 1)->firstOrFail();
Auth::loginUsingId($produccion->id);

$vistaIndex = (new ReporteController)->index();
check($vistaIndex instanceof \Illuminate\View\View, 'index devuelve la vista');
$htmlIndex = $vistaIndex->render();
check(str_contains($htmlIndex, 'Avance por Obra'), 'el indice muestra la tarjeta de Avance por Obra');
check(str_contains($htmlIndex, 'Dictámenes de Calidad'), 'el indice muestra la tarjeta de Dictamenes');
check(str_contains($htmlIndex, route('reportes.avance-obra')), 'la tarjeta enlaza al reporte');

echo "== Indice de reportes (Calidad) ==\n";
$calidad = User::where('rol_id', 2)->firstOrFail();
Auth::loginUsingId($calidad->id);
$htmlCalidad = (new ReporteController)->index()->render();
check(str_contains($htmlCalidad, 'Avance por Obra'), 'Calidad tambien ve el reporte de avance');
check(str_contains($htmlCalidad, 'Dictámenes de Calidad'), 'Calidad ve su reporte de dictamenes');

echo "== Datos base para el reporte ==\n";
Auth::loginUsingId($produccion->id);

$obra = Obra::create([
    'codigo_obra'      => 'TEST-RPT-001',
    'nombre_obra'      => 'Obra Reporte Test',
    'cliente'          => 'Cliente Reporte',
    'fecha_inicio'     => now()->subDays(30)->toDateString(),
    'fecha_compromiso' => now()->addDays(30)->toDateString(),
    'estatus'          => 'Activa',
]);
$pieza = Pieza::create([
    'obra_id'            => $obra->id,
    'tipo_estructura_id' => 1,
    'estatus_id'         => 1,
    'clave_marca_pieza'  => 'RPT1',
    'peso_kg'            => 100,
    'cantidad'           => 1,
]);

$totalFases = CatFase::count();
$completada = CatEstatusFase::where('nombre', 'Completada')->value('id');
$enProceso  = CatEstatusFase::where('nombre', 'En Proceso')->value('id');

// 1 de 3 fases completada => avance esperado = round(1/3*100) = 33.
FasePieza::create([
    'pieza_id' => $pieza->id, 'fase_id' => 1, 'operario_id' => 1,
    'usuario_captura_id' => 1, 'estatus_fase_id' => $completada, 'fecha_inicio' => now(),
]);
FasePieza::create([
    'pieza_id' => $pieza->id, 'fase_id' => 2, 'operario_id' => 1,
    'usuario_captura_id' => 1, 'estatus_fase_id' => $enProceso, 'fecha_inicio' => now(),
]);
FasePieza::create([
    'pieza_id' => $pieza->id, 'fase_id' => 3, 'operario_id' => 1,
    'usuario_captura_id' => 1, 'estatus_fase_id' => $enProceso, 'fecha_inicio' => now(),
]);

echo "== Reporte: avance por obra ==\n";
$vistaAvance = (new ReporteController)->avanceObra(xget());
check($vistaAvance instanceof \Illuminate\View\View, 'avanceObra devuelve la vista');
$htmlAvance = $vistaAvance->render();
check(str_contains($htmlAvance, 'TEST-RPT-001'), 'la tabla incluye la obra creada');
check(str_contains($htmlAvance, 'RPT1') === false, 'la tabla principal no lista piezas (solo obras)');
check(str_contains($htmlAvance, 'Cliente Reporte'), 'la tabla muestra el cliente');

$esperadoPct = (int) round(1 / $totalFases * 100);
check(str_contains($htmlAvance, $esperadoPct . '%'), "el avance de la obra es {$esperadoPct}% (1 de {$totalFases} fases)");

// KPIs: la obra aporta 1 completada de totalFases esperadas.
check(str_contains($htmlAvance, 'Fases'), 'se muestran los KPIs de fases');
check(str_contains($htmlAvance, 'Liberadas'), 'se muestra el KPI de marcas liberadas');

echo "== Filtro por obra ==\n";
$vistaFiltro = (new ReporteController)->avanceObra(xget(['obra_id' => $obra->id]));
$htmlFiltro = $vistaFiltro->render();
check(str_contains($htmlFiltro, 'TEST-RPT-001'), 'el filtro por obra_id incluye la obra elegida');
check(str_contains($htmlFiltro, 'selected'), 'el filtro marca la opcion seleccionada');

echo "== Export CSV ==\n";
$respCsv = (new ReporteController)->avanceObra(xget(['formato' => 'csv']));
check($respCsv instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'el formato csv devuelve streamed response');

ob_start();
$respCsv->sendContent();
$csv = ob_get_clean();
check(str_contains($csv, 'Código'), 'el CSV trae las cabeceras');
check(str_contains($csv, 'TEST-RPT-001'), 'el CSV incluye la obra');

echo "== Sin datos: no debe romper ==\n";
// Obra real pero sin piezas => no aparece avance, la vista no debe romper.
$obraVacia = Obra::create([
    'codigo_obra'      => 'TEST-RPT-EMPTY',
    'nombre_obra'      => 'Obra Sin Datos',
    'cliente'          => 'Cliente Sin Datos',
    'fecha_inicio'     => now()->toDateString(),
    'fecha_compromiso' => now()->addDays(10)->toDateString(),
    'estatus'          => 'Pendiente',
]);
$vistaVacia = (new ReporteController)->avanceObra(xget(['obra_id' => $obraVacia->id]));
check($vistaVacia instanceof \Illuminate\View\View, 'una obra sin avance devuelve vista sin error');
check(str_contains($vistaVacia->render(), '0%'), 'el avance de una obra sin fases es 0%');

echo "== Reporte: dictamenes de calidad ==\n";
// Dos dictamenes en la fase creada: uno Rechazado y uno Aprobado.
$fase = FasePieza::where('pieza_id', $pieza->id)->where('fase_id', 1)->firstOrFail();

\App\Models\ObservacionCalidad::create([
    'fase_pieza_id' => $fase->id, 'usuario_id' => $calidad->id,
    'descripcion' => 'hallazgo rechazado', 'resultado' => 'Rechazado', 'created_at' => now()->subDays(2),
]);
\App\Models\ObservacionCalidad::create([
    'fase_pieza_id' => $fase->id, 'usuario_id' => $calidad->id,
    'descripcion' => 'hallazgo aprobado', 'resultado' => 'Aprobado', 'created_at' => now()->subDay(),
]);

$vistaCal = (new ReporteController)->calidad(xget());
check($vistaCal instanceof \Illuminate\View\View, 'calidad devuelve la vista');
$htmlCal = $vistaCal->render();
check(str_contains($htmlCal, 'hallazgo rechazado'), 'el reporte lista el dictamen rechazado');
check(str_contains($htmlCal, 'hallazgo aprobado'), 'el reporte lista el dictamen aprobado');
check(str_contains($htmlCal, 'TEST-RPT-001'), 'el reporte agrupa por obra');
check(str_contains($htmlCal, 'Tasa de rechazo'), 'se muestra la seccion de tasa de rechazo');

echo "== Filtro por resultado ==\n";
$htmlSoloRech = (new ReporteController)->calidad(xget(['resultado' => 'Rechazado']))->render();
check(str_contains($htmlSoloRech, 'hallazgo rechazado'), 'el filtro por resultado incluye el rechazado');
check(! str_contains($htmlSoloRech, 'hallazgo aprobado'), 'el filtro por resultado excluye el aprobado');

echo "== Filtro por obra (calidad) ==\n";
$htmlObraCal = (new ReporteController)->calidad(xget(['obra_id' => $obra->id]))->render();
check(str_contains($htmlObraCal, 'hallazgo aprobado'), 'el filtro por obra incluye dictamenes de esa obra');
$htmlOtraObra = (new ReporteController)->calidad(xget(['obra_id' => $obraVacia->id]))->render();
check(! str_contains($htmlOtraObra, 'hallazgo aprobado'), 'el filtro por otra obra los excluye');

echo "== Export CSV (calidad) ==\n";
$respCsvCal = (new ReporteController)->calidad(xget(['formato' => 'csv']));
check($respCsvCal instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'el CSV de calidad es streamed');
ob_start();
$respCsvCal->sendContent();
$csvCal = ob_get_clean();
check(str_contains($csvCal, 'Inspector'), 'el CSV de calidad trae cabeceras');
check(str_contains($csvCal, 'hallazgo aprobado'), 'el CSV de calidad incluye el dictamen');

echo "== Reporte: productividad por operario ==\n";
check(Route::has('reportes.productividad'), 'ruta reportes.productividad registrada');

$operario = \App\Models\Operario::firstOrFail();
// La fase 1 ya esta Completada, pero el reporte exige fecha_termino.
$fase->update(['fecha_termino' => now()->subDay()]);

$vistaProd = (new ReporteController)->productividad(xget());
check($vistaProd instanceof \Illuminate\View\View, 'productividad devuelve la vista');
$htmlProd = $vistaProd->render();
check(str_contains($htmlProd, $operario->nombre), 'el reporte lista al operario con fase cerrada');
check(str_contains($htmlProd, $operario->especialidadDerivada()), 'el reporte muestra la especialidad derivada');
check(str_contains($htmlProd, 'Resumen por especialidad'), 'se muestra el resumen por especialidad');
check(str_contains($htmlProd, 'Días activo'), 'la tabla incluye la columna de dias activo');

echo "== Filtro por operario (productividad) ==\n";
$htmlConOp = (new ReporteController)->productividad(xget(['operario_id' => $operario->id]))->render();
check(str_contains($htmlConOp, $operario->nombre), 'el filtro por operario incluye sus fases');

echo "== Export CSV (productividad) ==\n";
$respCsvProd = (new ReporteController)->productividad(xget(['formato' => 'csv']));
check($respCsvProd instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'el CSV de productividad es streamed');
ob_start();
$respCsvProd->sendContent();
$csvProd = ob_get_clean();
check(str_contains($csvProd, 'Especialidad'), 'el CSV de productividad trae cabeceras');
check(str_contains($csvProd, $operario->nombre), 'el CSV de productividad incluye al operario');

echo "== Reporte: hallazgos por fase y obra ==\n";
check(Route::has('reportes.hallazgos'), 'ruta reportes.hallazgos registrada');

$vistaHal = (new ReporteController)->hallazgos(xget());
check($vistaHal instanceof \Illuminate\View\View, 'hallazgos devuelve la vista');
$htmlHal = $vistaHal->render();
// Ya hay 2 dictamenes creados (1 rechazado, 1 aprobado) en la fase Armado del test.
check(str_contains($htmlHal, 'Concentración por fase'), 'se muestra la tabla por fase');
check(str_contains($htmlHal, 'Obras con más hallazgos'), 'se muestra el ranking por obra');
check(str_contains($htmlHal, 'Fase crítica'), 'se muestra el KPI de fase critica');
check(str_contains($htmlHal, 'Armado'), 'el reporte lista la fase Armado');
check(str_contains($htmlHal, 'TEST-RPT-001'), 'el ranking por obra incluye la obra del test');

echo "== Filtro por fase (hallazgos) ==\n";
$faseArmadoId = CatFase::where('nombre', 'Armado')->value('id');
$htmlFaseHal = (new ReporteController)->hallazgos(xget(['fase_id' => $faseArmadoId]))->render();
check(str_contains($htmlFaseHal, 'Armado'), 'el filtro por fase mantiene la fase elegida');

echo "== Export CSV (hallazgos) ==\n";
$respCsvHal = (new ReporteController)->hallazgos(xget(['formato' => 'csv']));
check($respCsvHal instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'el CSV de hallazgos es streamed');
ob_start();
$respCsvHal->sendContent();
$csvHal = ob_get_clean();
check(str_contains($csvHal, 'Hallazgos'), 'el CSV de hallazgos trae cabeceras');
check(str_contains($csvHal, 'Armado'), 'el CSV de hallazgos incluye la fase');

echo "== Reporte: historial de marca ==\n";
check(Route::has('reportes.historial'), 'ruta reportes.historial registrada');

// Sin pieza_id: la vista debe pedir seleccionar una marca y no romper.
$vistaHistVacia = (new ReporteController)->historial(xget());
check($vistaHistVacia instanceof \Illuminate\View\View, 'historial devuelve la vista');
$htmlHistVacia = $vistaHistVacia->render();
check(str_contains($htmlHistVacia, 'Selecciona una marca'), 'sin marca pide seleccionar una');

// La pieza del test tiene 3 fases y 2 dictamenes (1 rechazado, 1 aprobado).
$vistaHist = (new ReporteController)->historial(xget(['pieza_id' => $pieza->id]));
check($vistaHist instanceof \Illuminate\View\View, 'historial con pieza devuelve la vista');
$htmlHist = $vistaHist->render();
check(str_contains($htmlHist, 'RPT1'), 'el encabezado muestra la clave de marca');
check(str_contains($htmlHist, 'TEST-RPT-001'), 'el encabezado muestra la obra');
check(str_contains($htmlHist, 'Dictamen de calidad'), 'la bitacora lista dictamenes');
check(str_contains($htmlHist, 'Fase iniciada'), 'la bitacora lista inicios de fase');
check(str_contains($htmlHist, 'avance'), 'el encabezado muestra el porcentaje de avance');

echo "== Filtro por obra (historial) ==\n";
$htmlHistObra = (new ReporteController)->historial(xget(['obra_id' => $obra->id]))->render();
check(str_contains($htmlHistObra, 'RPT1'), 'el selector de marca incluye la pieza de esa obra');

echo "== Export CSV (historial) ==\n";
$respCsvHist = (new ReporteController)->historial(xget(['pieza_id' => $pieza->id, 'formato' => 'csv']));
check($respCsvHist instanceof \Symfony\Component\HttpFoundation\StreamedResponse, 'el CSV de historial es streamed');
ob_start();
$respCsvHist->sendContent();
$csvHist = ob_get_clean();
check(str_contains($csvHist, 'Evento'), 'el CSV de historial trae cabeceras');
check(str_contains($csvHist, 'Dictamen de calidad'), 'el CSV de historial incluye los eventos');

echo "== Graficas (Fase 6, Chart.js local) ==\n";
check(file_exists(public_path('vendor/chartjs/chart.umd.min.js')), 'Chart.js esta disponible localmente (sin CDN)');

// Avance por obra: dos graficas (barras + dona del semaforo).
$htmlAvanceG = (new ReporteController)->avanceObra(xget())->render();
check(str_contains($htmlAvanceG, 'vendor/chartjs/chart.umd.min.js'), 'avance-obra carga Chart.js local');
check(str_contains($htmlAvanceG, 'id="graficaAvance"'), 'avance-obra incluye el canvas de avance');
check(str_contains($htmlAvanceG, 'id="graficaSemaforo"'), 'avance-obra incluye el canvas del semaforo');
check(str_contains($htmlAvanceG, 'graficaAvance-datos'), 'avance-obra inyecta los datos de la grafica');

// Calidad: dona de resultados + barras de rechazo por obra.
$htmlCalidadG = (new ReporteController)->calidad(xget())->render();
check(str_contains($htmlCalidadG, 'id="graficaResultados"'), 'calidad incluye la dona de resultados');
check(str_contains($htmlCalidadG, 'id="graficaRechazoObra"'), 'calidad incluye las barras de rechazo por obra');

// Hallazgos: barras apiladas por fase.
$htmlHalG = (new ReporteController)->hallazgos(xget())->render();
check(str_contains($htmlHalG, 'id="graficaHallazgosFase"'), 'hallazgos incluye las barras apiladas por fase');
check(str_contains($htmlHalG, 'barras-apiladas'), 'hallazgos usa el tipo barras apiladas');

// Productividad: barras de fases por operario.
$htmlProdG = (new ReporteController)->productividad(xget())->render();
check(str_contains($htmlProdG, 'id="graficaProductividad"'), 'productividad incluye las barras por operario');

// Degradacion elegante: sin datos, el partial no pinta canvas y muestra aviso.
$htmlProdVacio = (new ReporteController)->productividad(xget(['desde' => '2099-01-01', 'hasta' => '2099-12-31']))->render();
check(str_contains($htmlProdVacio, 'Sin datos para graficar'), 'sin datos la grafica muestra el aviso, no un canvas vacio');
check(str_contains($htmlProdVacio, 'id="graficaProductividad"') === false, 'sin datos no se pinta el canvas');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

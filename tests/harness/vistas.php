<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FasePiezaController;
use App\Http\Controllers\ObraController;
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

echo "== Render vistas: Jefe de Produccion ==\n";
Auth::loginUsingId(1);

$h = (new DashboardController)->index()->render();
check(str_contains($h, 'Obras Activas'), 'dashboard: KPI Obras Activas');
check(str_contains($h, 'Cumplimiento General del Taller'), 'dashboard: panel RF-03 general');
check(str_contains($h, 'Cumplimiento por Obra'), 'dashboard: tarjetas RF-03 por obra');
check(str_contains($h, '/vendor/alpine/alpine.min.js'), 'dashboard: Alpine local (sin CDN)');
check(str_contains($h, 'id="menu-lateral"'), 'dashboard: menu lateral presente');

$h = (new ObraController)->index()->render();
check(str_contains($h, 'Proyectos / Obras'), 'obras.index: titulo');
check(str_contains($h, 'OB-2026-001'), 'obras.index: obra real listada');
check(str_contains($h, 'bg-green-100 text-green-700'), 'obras.index: chip verde para obra Activa');
check(str_contains($h, 'id="menu-lateral"'), 'obras.index: menu lateral');

$h = (new ObraController)->create()->render();
check(str_contains($h, 'Registrar Nueva Obra'), 'obras.create: titulo');
check(str_contains($h, 'codigo_obra') && str_contains($h, 'fecha_compromiso'), 'obras.create: campos del formulario');

$h = (new ObraController)->show(1)->render();
check(str_contains($h, 'Avance acumulado de la obra'), 'obras.show: panel RF-03 avance de obra');
check(str_contains($h, 'Fase Actual'), 'obras.show: columna fase actual por pieza');
check(str_contains($h, 'bg-green-100 text-green-700'), 'obras.show: chip de estatus de obra');

$h = (new FasePiezaController)->show(1)->render();
check(str_contains($h, 'Marca: TB1'), 'piezas.show TB1: titulo de marca');
check(str_contains($h, '+ Registrar Avance en Taller'), 'piezas.show TB1: produccion ve formulario de avance');
check(str_contains($h, 'Fases Finalizadas'), 'piezas.show TB1: columna de fases finalizadas');

$h = (new FasePiezaController)->show(2)->render();
check(str_contains($h, 'Marca: TB2'), 'piezas.show TB2: titulo de marca');
check(str_contains($h, 'Fases Activas / En Evaluacion'), 'piezas.show TB2: fases activas');

echo "== Render vistas: Jefe de Calidad ==\n";
Auth::loginUsingId(2);

$h = (new FasePiezaController)->show(2)->render();
check(str_contains($h, 'Marca: TB2'), 'calidad: piezas.show TB2 accesible');
check(str_contains($h, 'Evaluar Calidad de la Fase'), 'calidad: formula de dictamen presente (Pintura en reproceso)');
check(str_contains($h, 'Dictaminar'), 'calidad: boton Dictaminar presente');
check(! str_contains($h, '+ Registrar Avance en Taller'), 'calidad: NO ve el formulario de avance (gate produccion)');

$h = (new FasePiezaController)->show(1)->render();
check(str_contains($h, 'Marca: TB1'), 'calidad: piezas.show TB1 (terminada) accesible');
check(str_contains($h, 'Cerrada por Producción. No admite'), 'calidad: fase terminada en solo lectura');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}
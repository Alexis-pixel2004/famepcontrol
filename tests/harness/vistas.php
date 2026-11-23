<?php

use App\Http\Controllers\AuthController;
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

function checkBotonHistorial(string $html, string $prefijo): void
{
    check(
        substr_count($html, 'aria-label="Actualizar historial de procesos e inspecciones"') === 1,
        "$prefijo: botón de actualizar historial presente una sola vez"
    );
    check(str_contains($html, 'Actualizar historial</span>'), "$prefijo: texto visible del botón");
    check(str_contains($html, 'window.location.reload()'), "$prefijo: botón recarga la página");
    check(str_contains($html, ':disabled="actualizando"'), "$prefijo: botón se deshabilita mientras actualiza");
}

echo "== Render vista: Login ==\n";
$h = (new AuthController)->showLogin()->render();
check(str_contains($h, asset('images/logo-famep.png')), 'auth.login: imagen local del logo');
check(str_contains($h, 'alt="Logo de FAMEP"'), 'auth.login: alt del logo');
check(str_contains($h, 'width="1920"') && str_contains($h, 'height="720"'), 'auth.login: dimensiones declaradas');
check(is_file(public_path('images/logo-famep.png')), 'auth.login: archivo de imagen existe');

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
check(str_contains($h, 'data-operario-id="1"'), 'piezas.show TB1: el formulario expone el operario asignado');
checkBotonHistorial($h, 'produccion');

$jsFases = file_get_contents(public_path('js/fases-evaluacion.js'));
check(str_contains($jsFases, 'selectOperario.value = operarioActualId'), 'JS preselecciona el operario asignado');

// La semilla real solo dejo la pieza TB1 (id 1), con Pintura en reproceso.
// Antes habia una TB2 de prueba que ya no existe: se reancla a la pieza real.
$h = (new FasePiezaController)->show(1)->render();
check(str_contains($h, 'Marca: TB1'), 'piezas.show TB1: titulo de marca');
check(str_contains($h, 'Fases Activas / En Evaluacion'), 'piezas.show TB1: fases activas (Pintura en reproceso)');

echo "== Render vistas: Jefe de Calidad ==\n";
Auth::loginUsingId(2);

$h = (new FasePiezaController)->show(1)->render();
check(str_contains($h, 'Marca: TB1'), 'calidad: piezas.show TB1 accesible');
check(str_contains($h, 'Evaluar Calidad de la Fase'), 'calidad: formula de dictamen presente (Pintura en reproceso)');
check(str_contains($h, 'Dictaminar'), 'calidad: boton Dictaminar presente');
check(! str_contains($h, '+ Registrar Avance en Taller'), 'calidad: NO ve el formulario de avance (gate produccion)');
check(str_contains($h, 'Cerrada por Producción. No admite'), 'calidad: fases cerradas en solo lectura');
checkBotonHistorial($h, 'calidad');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}
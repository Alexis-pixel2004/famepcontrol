<?php

use App\Support\FechaLocal;
use Illuminate\Support\Carbon;

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

echo "== Zona horaria: almacenamiento UTC y presentacion local ==\n";

check(FechaLocal::zona() === 'America/Mexico_City', 'zona de presentacion = America/Mexico_City (obtenido: ' . FechaLocal::zona() . ')');

// 22/10/2026 23:09 UTC = 22/10/2026 17:09 en Mexico (UTC-6).
$utcNoche = Carbon::parse('2026-10-22 23:09:43', 'UTC');
check(FechaLocal::formato($utcNoche, 'd/m/Y H:i') === '22/10/2026 17:09', '23:09 UTC se muestra como 17:09 local');

// 28/09/2026 00:23 UTC = 27/09/2026 18:23 local: cruza el cambio de dia.
$utcMadrugada = Carbon::parse('2026-09-28 00:23:16', 'UTC');
check(FechaLocal::formato($utcMadrugada, 'd/m/Y H:i') === '27/09/2026 18:23', '00:23 UTC del 28 se muestra como 18:23 del 27 local');

$inicio = FechaLocal::inicioDiaUtc('2026-09-27');
$fin    = FechaLocal::finDiaUtc('2026-09-27');

check($inicio->format('Y-m-d H:i:s') === '2026-09-27 06:00:00', 'inicio del dia local 27/09 = 06:00 UTC');
check($fin->format('Y-m-d H:i:s') === '2026-09-28 05:59:59', 'fin del dia local 27/09 = 05:59:59 UTC del 28');

check($utcMadrugada->betweenIncluded($inicio, $fin), 'un evento de las 18:23 local del 27/09 entra en el filtro del 27/09');

$hoyLocal = now()->timezone(FechaLocal::zona())->toDateString();
check(FechaLocal::hoyUtc()->toDateString() === $hoyLocal, 'hoy UTC coincide con la fecha local actual (obtenido: ' . $hoyLocal . ')');

// El mensaje de WhatsApp usa la misma conversion que la interfaz.
check(FechaLocal::ahora('d/m/Y H:i') === now()->timezone(FechaLocal::zona())->format('d/m/Y H:i'), 'la fecha de WhatsApp usa la hora local');

echo "\nResultado: {$GLOBALS['ok']} OK, {$GLOBALS['fail']} FAIL\n";

if ($GLOBALS['fail'] > 0) {
    throw new Exception("{$GLOBALS['fail']} verificaciones fallaron.");
}

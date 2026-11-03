<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * La aplicación y PostgreSQL trabajan en UTC. Esta clase centraliza la
 * conversión a la zona horaria con la que se presenta la información al
 * usuario, sin alterar los valores almacenados ni las comparaciones internas.
 */
final class FechaLocal
{
    public static function zona(): string
    {
        return config('app.display_timezone', 'America/Mexico_City');
    }

    /**
     * Formatea una fecha UTC en la zona local de presentación.
     */
    public static function formato(?CarbonInterface $fecha, string $formato = 'd/m/Y H:i'): string
    {
        if ($fecha === null) {
            return '—';
        }

        return $fecha->copy()->timezone(self::zona())->format($formato);
    }

    /**
     * Fecha y hora actuales en la zona local, ya formateadas.
     */
    public static function ahora(string $formato = 'd/m/Y H:i'): string
    {
        return now()->timezone(self::zona())->format($formato);
    }

    /**
     * Inicio (medianoche local) de un día expresado en UTC, para filtrar
     * columnas timestamp que se guardan en UTC.
     */
    public static function inicioDiaUtc(?string $fecha): ?Carbon
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        return Carbon::parse($fecha, self::zona())->startOfDay()->utc();
    }

    /**
     * Fin (23:59:59 local) de un día expresado en UTC.
     */
    public static function finDiaUtc(?string $fecha): ?Carbon
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        return Carbon::parse($fecha, self::zona())->endOfDay()->utc();
    }

    /**
     * Medianoche UTC del día local actual. Se usa para comparar contra
     * columnas DATE, que no llevan zona horaria.
     */
    public static function hoyUtc(): Carbon
    {
        return Carbon::parse(now()->timezone(self::zona())->toDateString(), 'UTC')->startOfDay();
    }
}

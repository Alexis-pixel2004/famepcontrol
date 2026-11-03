<?php

namespace App\Services;

use App\Models\LogAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Auditoría de escrituras (Nivel 1 + Nivel 2).
 *
 * Nivel 1: se engancha a los eventos Eloquent (created/updated/deleted) de
 *          los modelos núcleo vía AppServiceProvider. Es automático.
 * Nivel 2: registro explícito desde controllers para eventos sin cambio de
 *          fila (ej. cambio de contraseña).
 *
 * Reglas:
 * - Solo ESCRITURAS: crear, editar y eliminar. Las lecturas no se auditan.
 * - Si un "update" no cambió nada relevante (solo campos derivados o
 *   idénticos), NO se registra: se evita el ruido de los recálculos.
 * - Nunca se guardan campos sensibles (password_hash, etc.).
 */
class AuditoriaService
{
    /**
     * Campos que nunca se auditan: son derivados/automáticos o sensibles.
     * Las claves son "tabla.campo"; '*' es el comodín por tabla.
     */
    private const CAMPOS_IGNORADOS = [
        // Derivados por recalcularEstatus()/recalcularEstadoDesdeDictamenes().
        'obras.estatus',
        'piezas.estatus_id',
        'fases_pieza.estatus_fase_id',
        'fases_pieza.fecha_dictamen',
        // Nada sensible debe llegar aquí; se ignora por precaución.
        'usuarios.password_hash',
    ];

    /**
     * Mapa tabla => etiqueta legible de la entidad, para el reporte.
     */
    public const ENTIDADES = [
        'obras'                 => 'Obra',
        'piezas'                => 'Pieza',
        'fases_pieza'           => 'Fase de Pieza',
        'observaciones_calidad' => 'Dictamen de Calidad',
        'operarios'             => 'Operario',
        'operario_fase'         => 'Fases habilitadas',
        'usuarios'              => 'Usuario',
    ];

    /**
     * Registra una acción sobre un modelo (Nivel 1). Devuelve true si
     * efectivamente se escribió un log (útil para tests).
     */
    public static function registrarModelo(Model $modelo, string $accion): bool
    {
        $tabla = $modelo->getTable();

        $previos = null;
        $nuevos  = null;

        if ($accion === 'creado') {
            $nuevos = self::limpiar($tabla, $modelo->getAttributes());
        } elseif ($accion === 'eliminado') {
            $previos = self::limpiar($tabla, $modelo->getOriginal());
        } else {
            // 'editado': solo los campos que realmente cambiaron.
            $cambiados = self::camposCambiados($tabla, $modelo);

            if (empty($cambiados)) {
                return false; // sin cambio relevante: no es una acción del usuario
            }

            $original = $modelo->getOriginal();
            foreach ($cambiados as $campo) {
                $previos[$campo] = self::normalizar($original[$campo] ?? null);
                $nuevos[$campo]  = self::normalizar($modelo->getAttribute($campo));
            }
        }

        if (empty($previos) && empty($nuevos)) {
            return false;
        }

        return self::escribir($tabla, $modelo->getKey(), $accion, $previos, $nuevos);
    }

    /**
     * Registro explícito (Nivel 2): eventos sin cambio de fila.
     */
    public static function registrar(
        string $accion,
        string $tabla,
        ?int $registroId = null,
        ?array $previos = null,
        ?array $nuevos = null
    ): bool {
        return self::escribir($tabla, $registroId, $accion, $previos, $nuevos);
    }

    /**
     * Escritura física del log. Nunca falla el flujo de negocio: si algo
     * va mal, se ignora silenciosamente para no romper la operación.
     */
    private static function escribir(
        string $tabla,
        $registroId,
        string $accion,
        ?array $previos,
        ?array $nuevos
    ): bool {
        try {
            LogAuditoria::create([
                'usuario_id'     => Auth::id(),
                'accion'         => $accion,
                'tabla_afectada' => $tabla,
                'registro_id'    => $registroId !== null ? (int) $registroId : null,
                'datos_previos'  => $previos,
                'datos_nuevos'   => $nuevos,
                'fecha_hora'     => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Campos cuyo valor cambió de verdad, excluyendo los ignorados.
     */
    private static function camposCambiados(string $tabla, Model $modelo): array
    {
        $cambiados = [];

        foreach ($modelo->getChanges() as $campo => $valorNuevo) {
            if (self::ignorado($tabla, $campo)) {
                continue;
            }

            $valorViejo = $modelo->getOriginal($campo);

            if (self::normalizar($valorViejo) === self::normalizar($valorNuevo)) {
                continue;
            }

            $cambiados[] = $campo;
        }

        return $cambiados;
    }

    /**
     * Quita campos ignorados y normaliza valores no serializables.
     */
    private static function limpiar(string $tabla, array $atributos): array
    {
        $limpio = [];

        foreach ($atributos as $campo => $valor) {
            if (self::ignorado($tabla, $campo)) {
                continue;
            }

            $limpio[$campo] = self::normalizar($valor);
        }

        return $limpio;
    }

    private static function ignorado(string $tabla, string $campo): bool
    {
        if (in_array($campo, ['id', 'created_at', 'updated_at'], true)) {
            return true;
        }

        return in_array("{$tabla}.{$campo}", self::CAMPOS_IGNORADOS, true);
    }

    /**
     * Convierte fechas/objetos a algo serializable en jsonb.
     */
    private static function normalizar($valor)
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_object($valor)) {
            return method_exists($valor, '__toString') ? (string) $valor : null;
        }

        return $valor;
    }
}

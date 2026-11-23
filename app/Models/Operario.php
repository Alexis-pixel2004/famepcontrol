<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Operario extends Model
{
    use HasFactory;

    protected $table = 'operarios';

    protected $fillable = [
        'clave_empleado',
        'nombre',
        'apellido',
        'especialidad',
        'activo',
    ];
    public $timestamps = false;

    // Relación: Un operario participa en múltiples fases de piezas
    public function fases()
    {
        return $this->hasMany(FasePieza::class, 'operario_id');
    }

    // Fases que este operario está habilitado a ejecutar (N:M).
    // Un operario multifase (ej. soldador/pintor) tendrá varias; uno de una
    // sola etapa tendrá una. Sin filas = sin restricción (comodidad de arranque).
    public function fasesHabilitadas()
    {
        return $this->belongsToMany(CatFase::class, 'operario_fase', 'operario_id', 'fase_id')
            ->orderBy('orden_secuencia');
    }

    // Estados de fase que mantienen al operario ocupado: mientras la fase
    // esté en trabajo, en retrabajo o aprobada pero sin cierre de Producción,
    // no se le puede asignar otra tarea.
    public const ESTADOS_QUE_OCUPAN = ['En Proceso', 'En Reproceso', 'Aprobada por Calidad'];

    /**
     * Primera fase no terminal asignada al operario. Se puede excluir la
     * fase que se está guardando para permitir retomar un reproceso con
     * el mismo operario.
     */
    public function faseActiva(?int $excluirFasePiezaId = null): ?FasePieza
    {
        return $this->fases()
            ->when($excluirFasePiezaId, fn ($q) => $q->where('id', '!=', $excluirFasePiezaId))
            ->whereHas('estatusFase', fn ($q) => $q->whereIn('nombre', self::ESTADOS_QUE_OCUPAN))
            ->with(['pieza.obra', 'fase', 'estatusFase'])
            ->orderBy('id')
            ->first();
    }

    // ¿Puede este operario ejecutar la fase indicada? (por id)
    public function puedeHacerFase($faseId): bool
    {
        // Si aún no se le configuró ninguna fase, no se restringe (compatibilidad).
        if ($this->fasesHabilitadas()->count() === 0) {
            return true;
        }

        return $this->fasesHabilitadas()->whereKey($faseId)->exists();
    }

    // Especialidad derivada: se calcula a partir de sus fases habilitadas.
    // Un operario multifase las lista unidas por " / "; si no tiene fases
    // configuradas se conserva la especialidad histórica como respaldo.
    public function especialidadDerivada(): string
    {
        $nombres = $this->fasesHabilitadas()->pluck('nombre')->all();

        if (! empty($nombres)) {
            return implode(' / ', $nombres);
        }

        return $this->especialidad ?? 'Sin fases asignadas';
    }

    // Accessor Eloquent para poder usar $operario->especialidadDerivada en Blade.
    public function getEspecialidadDerivadaAttribute(): string
    {
        return $this->especialidadDerivada();
    }
}

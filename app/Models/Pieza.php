<?php

namespace App\Models;

use App\Support\FechaLocal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pieza extends Model
{
    use HasFactory;

    protected $table = 'piezas';

    // Campos permitidos para inserción masiva
    protected $fillable = [
        'obra_id',
        'tipo_estructura_id',
        'estatus_id',
        'clave_marca_pieza',
        'peso_kg',
        'cantidad',
    ];
    public $timestamps = false;

    // Relación: La pieza pertenece a una Obra
    public function obra()
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    // Relación: Tipo de Estructura (Pesada, Semipesada, Ligera)
    public function tipoEstructura()
    {
        return $this->belongsTo(CatTipoEstructura::class, 'tipo_estructura_id');
    }

    // Relación: Estatus actual (Pendiente, En Proceso, etc.)
    public function estatus()
    {
        return $this->belongsTo(CatEstatusPieza::class, 'estatus_id');
    }

    // Relación: Histórico de Fases (Armado, Soldadura, Pintura)
    public function fases()
    {
        return $this->hasMany(FasePieza::class, 'pieza_id');
    }

    /**
     * Deriva el estatus de la pieza a partir del estado de sus fases.
     * Compara contra TODAS las fases del catálogo, no contra las filas
     * existentes, para que una pieza con fases sin registrar nunca
     * quede como terminada.
     */
    public function recalcularEstatus(): void
    {
        $totalFases = CatFase::count();

        $registradas = $this->fases()->count();

        $completadas = $this->fases()
            ->whereHas('estatusFase', fn ($q) => $q->where('es_completada', true))
            ->count();

        if ($registradas === 0) {
            $nombre = 'Pendiente';
        } elseif ($this->fases()->whereHas('estatusFase', fn ($q) => $q->where('es_rechazada', true))->exists()) {
            $nombre = 'Detenida';
        } elseif ($this->fases()->whereHas('estatusFase', fn ($q) => $q->where('en_reproceso', true))->exists()) {
            $nombre = 'En Revisión';
        } elseif ($totalFases > 0 && $completadas === $totalFases) {
            $nombre = 'Aprobada';
        } else {
            $nombre = 'En Proceso';
        }

        $estatusId = CatEstatusPieza::where('nombre', $nombre)->value('id');

        if ($estatusId !== null && (int) $this->estatus_id !== (int) $estatusId) {
            $this->forceFill(['estatus_id' => $estatusId])->save();
        }
    }

    /**
     * Fechas clave de la pieza para la cabecera: la más temprana de
     * producción y las más recientes de dictamen y cierre.
     */
    public function resumenFechas(): array
    {
        $formato = fn ($valor) => FechaLocal::formato($valor, 'd/m/Y');

        $inicio   = $this->fases->pluck('fecha_inicio')->filter()->sort()->first();
        $dictamen = $this->fases->pluck('fecha_dictamen')->filter()->sort()->last();
        $cierre   = $this->fases->pluck('fecha_termino')->filter()->sort()->last();

        return [
            ['etiqueta' => 'Inicio (Producción)',  'valor' => $formato($inicio)],
            ['etiqueta' => 'Dictamen (Calidad)',   'valor' => $formato($dictamen)],
            ['etiqueta' => 'Cierre (Producción)',  'valor' => $formato($cierre)],
        ];
    }

    /**
     * Número de fases que el catálogo exige por pieza (denominador del avance).
     */
    public static function totalFasesEsperadas(): int
    {
        static $total = null;

        return $total ??= CatFase::count();
    }

    /**
     * Porcentaje de cumplimiento de la pieza: fases completadas
     * sobre las fases del catálogo (no se pondera por peso/cantidad).
     */
    public function porcentajeAvance(): int
    {
        $esperadas = self::totalFasesEsperadas();

        if ($esperadas === 0) {
            return 0;
        }

        $completadas = $this->fases
            ->filter(fn ($fase) => (bool) $fase->estatusFase?->es_completada)
            ->count();

        return (int) round($completadas / $esperadas * 100);
    }

    /**
     * Fase que está en curso: la primera (por orden de secuencia) que
     * aún no está completada. null = sin fases registradas o terminada.
     */
    public function faseActual(): ?FasePieza
    {
        return $this->fases
            ->filter(fn ($fase) => ! (bool) $fase->estatusFase?->es_completada)
            ->sortBy(fn ($fase) => $fase->fase?->orden_secuencia ?? PHP_INT_MAX)
            ->first();
    }

    /**
     * true si la pieza no tiene ninguna fase registrada todavía.
     */
    public function sinFasesRegistradas(): bool
    {
        return $this->fases->isEmpty();
    }
}

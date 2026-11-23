<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FasePieza extends Model
{
    use HasFactory;

    protected $table = 'fases_pieza';

    public $timestamps = false;

    protected $fillable = [
        'pieza_id',
        'fase_id',
        'operario_id',
        'usuario_captura_id',
        'estatus_fase_id',
        'fecha_inicio',
        'fecha_dictamen',
        'fecha_termino',
    ];

    protected $casts = [
        'fecha_inicio'   => 'datetime',
        'fecha_dictamen' => 'datetime',
        'fecha_termino'  => 'datetime',
    ];

    // Relación: Pertenece a una Pieza
    public function pieza()
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }

    // Relación: Tipo de Fase (Armado, Soldadura, Pintura)
    public function fase()
    {
        return $this->belongsTo(CatFase::class, 'fase_id');
    }

    // Relación: Estado de la Fase (catálogo cat_estatus_fase)
    public function estatusFase()
    {
        return $this->belongsTo(CatEstatusFase::class, 'estatus_fase_id');
    }

    // Relación: Operario físico que realizó el trabajo
    public function operario()
    {
        return $this->belongsTo(Operario::class, 'operario_id');
    }

    // Relación: Usuario administrativo (Jefe) que capturó el avance
    public function usuarioCaptura()
    {
        return $this->belongsTo(User::class, 'usuario_captura_id');
    }

    // Relación: Hallazgos u observaciones de calidad en esta fase.
    // Orden descendente por la fecha efectiva más reciente: si el dictamen
    // fue editado, usa editado_at; si no, created_at. Así los recién editados
    // suben al tope sin perder el orden del resto. `id` desempata.
    public function observaciones()
    {
        return $this->hasMany(ObservacionCalidad::class, 'fase_pieza_id')
            ->orderByRaw('COALESCE(editado_at, created_at) DESC')
            ->orderByDesc('id');
    }

    public function evidencias()
    {
        return $this->hasMany(EvidenciaFotografica::class, 'fase_pieza_id');
    }

    /**
     * Deriva el estado de la fase a partir de sus dictámenes de calidad.
     * Se usa al editar o eliminar un dictamen, ya que ambas operaciones
     * pueden invalidar el estado que había dejado el dictamen original.
     *
     * Regla: manda el ÚLTIMO dictamen registrado de la fase.
     * - Aprobado             => Aprobada por Calidad
     * - Enviado a Reproceso  => En Reproceso
     * - Rechazado            => Rechazada
     * - Sin dictámenes       => En Proceso (Calidad no ha intervenido)
     *
     * Una fase "Completada" (cerrada por Producción tras la aprobación de
     * Calidad) no se reabre: es un estado posterior e independiente del
     * dictamen, por lo que se respeta tal cual.
     */
    /**
     * Nombre del estado de fase que corresponde a un resultado de dictamen.
     * Se centraliza para que el recálculo y las validaciones previas usen
     * exactamente la misma equivalencia.
     */
    public static function estadoParaResultado(?string $resultado): string
    {
        return match ($resultado) {
            'Aprobado'            => 'Aprobada por Calidad',
            'Enviado a Reproceso' => 'En Reproceso',
            'Rechazado'           => 'Rechazada',
            default               => 'En Proceso',
        };
    }

    public function recalcularEstadoDesdeDictamenes(): void
    {
        if ($this->terminada()) {
            return;
        }

        // "Último dictamen" = el de mayor id (orden de creación), sin importar
        // si alguno fue editado después. reorder() limpia el orden por defecto
        // de la relación (que prioriza editado_at) para no alterar esta regla.
        $ultimo = $this->observaciones()
            ->reorder('id', 'desc')
            ->value('resultado');

        $nombre = self::estadoParaResultado($ultimo);

        $estatusId = CatEstatusFase::where('nombre', $nombre)->value('id');

        if ($estatusId === null) {
            return;
        }

        // Solo se actualiza si cambia, evitando tocar fecha_dictamen cuando
        // el estado derivado coincide con el que ya tenía la fase.
        FasePieza::whereKey($this->getKey())
            ->where('estatus_fase_id', '!=', $estatusId)
            ->update(['estatus_fase_id' => $estatusId]);

        $this->estatus_fase_id = $estatusId;

        // Refresca la relación para que los helpers (aprobadaPorCalidad,
        // admiteDictamen, etc.) vean el nombre nuevo sin reconsultar.
        $this->unsetRelation('estatusFase');
    }

    // La fase ya no admite cambios de operario ni nuevos dictamenes
    public function estaCerrada(): bool
    {
        return (bool) $this->estatusFase?->bloquea_captura;
    }

    // Calidad ya aprobó y la fase espera el cierre de Producción
    public function aprobadaPorCalidad(): bool
    {
        return $this->estatusFase?->nombre === 'Aprobada por Calidad';
    }

    // Aprobada por Calidad y ya cerrada por Producción: no admite nada
    public function terminada(): bool
    {
        return $this->estatusFase?->nombre === 'Completada';
    }

    /**
     * Los dictámenes de una fase ya finalizada son inmutables (solo lectura):
     * no se pueden editar ni eliminar, para preservar el histórico de auditoría.
     *
     * La decisión vive aquí, en un solo punto, para que el controller y la
     * vista compartan exactamente la misma regla. Si más adelante se agrega
     * un rol admin con permiso de excepción, el override se implementa aquí.
     */
    public function dictamenesInmutables(): bool
    {
        return $this->terminada();
    }

    /**
     * Solo se puede dictaminar mientras la fase está en trabajo o en
     * reproceso. Una vez que Calidad aprobó (o la marca fue rechazada
     * o ya está cerrada) no se admiten nuevos dictámenes ni comentarios.
     */
    public function admiteDictamen(): bool
    {
        return in_array($this->estatusFase?->nombre, ['En Proceso', 'En Reproceso'], true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\CarbonInterface;

class ObservacionCalidad extends Model
{
    use HasFactory;

    protected $table = 'observaciones_calidad';

    // desactivar timestamp automatico
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'editado'    => 'boolean',
        'editado_at' => 'datetime',
    ];

    protected $fillable = [
        'fase_pieza_id',
        'usuario_id',
        'descripcion',
        'severidad',
        'corregido',
        'resultado',
        'editado',
        'editado_at',
        'editado_por',
    ];

    // Fecha efectiva del dictamen: si algún día existe fecha_registro la
    // prefiere; hoy solo existe created_at.
    public function fechaInspeccion(): ?CarbonInterface
    {
        return $this->fecha_registro ?? $this->created_at;
    }

    // Relación: Pertenece a una fase de pieza específica
    public function fasePieza()
    {
        return $this->belongsTo(FasePieza::class, 'fase_pieza_id');
    }

    // Relación: Jefe de Calidad que registró el hallazgo
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Relación: Fotos de evidencia adjuntas
    public function evidencias()
    {
        return $this->hasMany(EvidenciaFotografica::class, 'observacion_calidad_id');
    }
    // Relación con la tabla usuarios
    public function inspector()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Último usuario de Calidad que modificó este dictamen
    public function editadoPor()
    {
        return $this->belongsTo(User::class, 'editado_por');
    }

    // true si el dictamen fue modificado después de su registro original
    public function fueEditado(): bool
    {
        return (bool) $this->editado;
    }

    // Fecha/hora de la última modificación (null si nunca se editó)
    public function fechaEdicion(): ?CarbonInterface
    {
        return $this->editado_at;
    }
}

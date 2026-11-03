<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenciaFotografica extends Model
{
    use HasFactory;

    protected $table = 'evidencias_fotograficas';
    public $timestamps = false;

    protected $fillable = [
        'fase_pieza_id',
        'observacion_calidad_id',
        'ruta_archivo',
       
    ];

    public function fasePieza()
    {
        return $this->belongsTo(FasePieza::class, 'fase_pieza_id');
    }

    public function observacionCalidad()
    {
        return $this->belongsTo(ObservacionCalidad::class, 'observacion_calidad_id');
    }
}
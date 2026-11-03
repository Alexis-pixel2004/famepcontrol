<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Obra extends Model
{
    use HasFactory;

    protected $table = 'obras';

    // Campos permitidos para inserción masiva
    protected $fillable = [
        'codigo_obra',
        'nombre_obra',
        'cliente',
        'fecha_inicio',
        'fecha_compromiso',
        'estatus',
        'cliente_usuario_id',
    ];
    public $timestamps = false;

    protected $casts = [
        'fecha_inicio'      => 'date',
        'fecha_compromiso'  => 'date',
    ];

    // Relación: Una Obra tiene muchas Piezas
    public function piezas()
    {
        return $this->hasMany(Pieza::class, 'obra_id');
    }

    // Relación: Cuenta de cliente (Visitante) a la que se asigna la obra
    public function clienteUsuario()
    {
        return $this->belongsTo(User::class, 'cliente_usuario_id');
    }

    /**
     * Deriva el estatus de la obra a partir de sus piezas:
     * - Sin piezas o sin ninguna pieza con avance => Pendiente.
     * - Con avance y no todas las piezas aprobadas => Activa.
     * - Todas las piezas aprobadas => Finalizada.
     *
     * Se escribe con query builder y se sincroniza el atributo en memoria
     * para no dejar instancias obsoletas (mismo criterio que Pieza).
     */
    public function recalcularEstatus(): void
    {
        $total = $this->piezas()->count();

        if ($total === 0) {
            $nombre = 'Pendiente';
        } else {
            $conAvance = $this->piezas()->whereHas('fases')->exists();

            $aprobadas = $this->piezas()
                ->whereHas('estatus', fn ($q) => $q->where('nombre', 'Aprobada'))
                ->count();

            $nombre = ! $conAvance
                ? 'Pendiente'
                : ($aprobadas === $total ? 'Finalizada' : 'Activa');
        }

        Obra::whereKey($this->getKey())
            ->where('estatus', '!=', $nombre)
            ->update(['estatus' => $nombre]);

        $this->estatus = $nombre;
    }
}

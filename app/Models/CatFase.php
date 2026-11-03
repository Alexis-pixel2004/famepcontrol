<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatFase extends Model
{
    use HasFactory;

    protected $table = 'cat_fases';

    // Operarios habilitados a ejecutar esta fase (N:M).
    public function operarios()
    {
        return $this->belongsToMany(Operario::class, 'operario_fase', 'fase_id', 'operario_id');
    }
}
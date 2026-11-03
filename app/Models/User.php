<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nombre', 'email', 'password_hash', 'rol_id', 'activo', 'codigo_acceso'])]
#[Hidden(['password_hash', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    // Desactivar timestamps automáticos de Laravel
    public $timestamps = false;

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function rol()
    {
        return $this->belongsTo(CatRol::class, 'rol_id');
    }

    public function obrasAsignadas()
    {
        return $this->hasMany(Obra::class, 'cliente_usuario_id');
    }

    public function esCalidad(): bool
    {
        return (int) $this->rol_id === 2;
    }

    public function esProduccion(): bool
    {
        return (int) $this->rol_id === 1;
    }

    public function esCliente(): bool
    {
        return (int) $this->rol_id === 3;
    }
}
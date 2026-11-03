<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CatEstatusFase extends Model
{
    use HasFactory;

    protected $table = 'cat_estatus_fase';

    public $timestamps = false;
}

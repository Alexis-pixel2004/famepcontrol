<?php

namespace App\Providers;

use App\Models\FasePieza;
use App\Models\Obra;
use App\Models\ObservacionCalidad;
use App\Models\Operario;
use App\Models\Pieza;
use App\Services\AuditoriaService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registrarAuditoria();
    }

    /**
     * Auditoría de escrituras (Nivel 1): se engancha a los eventos Eloquent
     * de los modelos núcleo. El servicio decide si el cambio es relevante
     * (ignora campos derivados y updates sin diferencia real).
     *
     * Nota: los recálculos de estatus que usan el query builder
     * (Obra::whereKey()->update()) no disparan eventos, así que no generan
     * ruido. Pieza::recalcularEstatus() sí usa save(); su campo estatus_id
     * está en la lista de ignorados del servicio.
     */
    private function registrarAuditoria(): void
    {
        foreach ([Obra::class, Pieza::class, FasePieza::class, ObservacionCalidad::class, Operario::class] as $modelo) {
            $modelo::created(fn ($m) => AuditoriaService::registrarModelo($m, 'creado'));
            $modelo::updated(fn ($m) => AuditoriaService::registrarModelo($m, 'editado'));
            $modelo::deleted(fn ($m) => AuditoriaService::registrarModelo($m, 'eliminado'));
        }
    }
}

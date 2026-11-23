<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\Pieza;
use App\Models\CatFase;
use App\Models\ObservacionCalidad;
use App\Support\FechaLocal;
use Illuminate\Http\Request;
class DashboardController extends Controller
{
   public function index()
{
    // Conteos a nivel de Obras en el taller
    $obrasActivas     = Obra::where('estatus', 'Activa')->count();
    $obrasPendientes  = Obra::where('estatus', 'Pendiente')->count();
    $obrasFinalizadas = Obra::where('estatus', 'Finalizada')->count();
    $totalObras       = Obra::count();

    // Métrica: fases completadas / (fases del catálogo x piezas)
    $totalFases = CatFase::count();
    $piezas     = Pieza::with(['obra', 'estatus', 'fases.estatusFase', 'fases.fase'])->get();

    $completadasGlobal = $piezas->sum(
        fn ($p) => $p->fases->filter(fn ($f) => (bool) $f->estatusFase?->es_completada)->count()
    );
    $esperadasGlobal = $piezas->count() * $totalFases;

    $avanceTaller = [
        'completadas' => $completadasGlobal,
        'esperadas'   => $esperadasGlobal,
        'porcentaje'  => $esperadasGlobal > 0 ? (int) round($completadasGlobal / $esperadasGlobal * 100) : 0,
    ];

    // Avance por obra y fase actual por pieza
    $obrasAvance = Obra::orderBy('id')->get()->map(function (Obra $obra) use ($piezas, $totalFases) {
        $piezasDeLaObra = $piezas->where('obra_id', $obra->id)->values();

        $completadas  = $piezasDeLaObra->sum(
            fn ($p) => $p->fases->filter(fn ($f) => (bool) $f->estatusFase?->es_completada)->count()
        );
        $esperadas = $piezasDeLaObra->count() * $totalFases;

        return [
            'obra'          => $obra,
            'total_marcas'  => $piezasDeLaObra->count(),
            'liberadas'     => $piezasDeLaObra->filter(fn ($p) => $p->estatus?->nombre === 'Aprobada')->count(),
            'completadas'   => $completadas,
            'esperadas'     => $esperadas,
            'porcentaje'    => $esperadas > 0 ? (int) round($completadas / $esperadas * 100) : 0,
            'piezas'        => $piezasDeLaObra->map(function ($p) {
                return [
                    'pieza'         => $p,
                    'porcentaje'    => $p->porcentajeAvance(),
                    'fase_actual'   => $p->faseActual(),
                    'sin_inicio'    => $p->sinFasesRegistradas(),
                ];
            }),
        ];
    });

    // Listado reciente para la tabla inferior
    $obrasResumen = Obra::withCount('piezas')->latest()->take(5)->get();
    
    // Notificaciones para el header
    $notificacionesCalidad = ObservacionCalidad::with(['fasePieza.pieza.obra', 'inspector'])
        ->orderBy('id', 'desc')
        ->take(8)
        ->get();

    return view('dashboard', compact(
        'obrasActivas',
        'obrasPendientes',
        'obrasFinalizadas',
        'totalObras',
        'avanceTaller',
        'obrasAvance',
        'totalFases',
        'obrasResumen',
        'notificacionesCalidad'
    ));
}

    public function getNotificacionesApi()
{
    try {
        $notificaciones = ObservacionCalidad::with(['fasePieza.pieza'])
            ->orderBy('id', 'desc')
            ->take(8)
            ->get()
            ->map(function ($notif) {
                $fase = $notif->fasePieza;
                $pieza = $fase?->pieza;
                $obra = $pieza?->obra;

                return [
                    'id' => $notif->id,
                    'resultado' => $notif->resultado,
                    'descripcion' => $notif->descripcion,
                    'fecha_registro' => FechaLocal::formato($notif->created_at, 'd/m/Y h:i A'),
                    'inspector_nombre' => $notif->inspector->nombre ?? 'Inspector',
                    'obra_nombre' => $obra->nombre_obra ?? $obra->nombre ?? 'N/A',
                    'clave_marca_pieza' => $pieza->clave_marca_pieza ?? 'N/A',
                    'pieza_id' => $pieza->id ?? null,
                    'url_pieza' => ($pieza && \Route::has('piezas.show')) ? route('piezas.show', $pieza->id) : '#',
                ];
            });

        return response()->json($notificaciones);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}
}
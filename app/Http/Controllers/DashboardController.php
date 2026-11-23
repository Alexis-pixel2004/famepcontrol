<?php

namespace App\Http\Controllers;

use App\Models\CatFase;
use App\Models\FasePieza;
use App\Models\LogAuditoria;
use App\Models\Obra;
use App\Models\ObservacionCalidad;
use App\Models\Pieza;
use App\Support\FechaLocal;
use Illuminate\Support\Facades\Route;

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

    // Dictámenes recientes: panel histórico de Producción (RN-26).
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

    /**
     * Panel de notificaciones por rol (RN-26).
     *
     * Producción conserva su panel histórico con los dictámenes de Calidad.
     * Calidad ve actividad del taller desde logs_auditoria, sin sus propias
     * acciones. Otros roles (Portal) no reciben notificaciones.
     */
    public function getNotificacionesApi()
    {
        if (auth()->user()?->esCalidad()) {
            return $this->notificacionesActividadTaller();
        }

        if (auth()->user()?->esProduccion()) {
            return $this->notificacionesDictamenes();
        }

        return response()->json([]);
    }

    /**
     * Panel histórico de Producción: últimos dictámenes emitidos por Calidad.
     */
    private function notificacionesDictamenes()
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
                        'id'                => $notif->id,
                        'resultado'         => $notif->resultado,
                        'descripcion'       => $notif->descripcion,
                        'fecha_registro'    => FechaLocal::formato($notif->created_at, 'd/m/Y h:i A'),
                        'inspector_nombre'  => $notif->inspector->nombre ?? 'Inspector',
                        'obra_nombre'       => $obra->nombre_obra ?? $obra->nombre ?? 'N/A',
                        'clave_marca_pieza' => $pieza->clave_marca_pieza ?? 'N/A',
                        'pieza_id'          => $pieza->id ?? null,
                        'url_pieza'         => ($pieza && Route::has('piezas.show'))
                            ? route('piezas.show', $pieza->id)
                            : '#',
                    ];
                });

            return response()->json($notificaciones);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Actividad reciente del taller, exclusiva para Calidad.
     *
     * Se alimenta de logs_auditoria (id global y creciente) en lugar de
     * observaciones_calidad: así el badge y la clave de Alpine no chocan
     * entre entidades y el propio usuario no ve lo que él mismo hizo.
     */
    private function notificacionesActividadTaller()
    {
        try {
            $logs = LogAuditoria::with('usuario')
                ->whereIn('tabla_afectada', ['fases_pieza', 'piezas'])
                ->where('accion', 'creado')
                ->where('usuario_id', '!=', auth()->id())
                ->orderByDesc('fecha_hora')
                ->take(8)
                ->get();

            return response()->json(
                $logs->map(fn (LogAuditoria $log) => $this->notificacionDesdeAuditoria($log))->values()
            );
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Convierte un log de auditoría en el payload del dropdown.
     *
     * Si la pieza/fase ya fue eliminada, el log permanece (es historial):
     * se marca como registro_eliminado, se reconstruye el contexto desde
     * datos_nuevos y se omite el enlace para no dejar un link roto.
     */
    private function notificacionDesdeAuditoria(LogAuditoria $log): array
    {
        $datos = $log->datos_nuevos ?? [];
        $esPieza = $log->tabla_afectada === 'piezas';
        $pieza = null;
        $obra = null;
        $eliminado = false;

        if ($esPieza) {
            $pieza = Pieza::with('obra')->find($log->registro_id);

            if ($pieza) {
                $obra = $pieza->obra;
                $marca = $pieza->clave_marca_pieza;
            } else {
                $eliminado = true;
                $marca = $datos['clave_marca_pieza'] ?? 'Marca eliminada';
                $obra = ! empty($datos['obra_id']) ? Obra::find($datos['obra_id']) : null;
            }

            $titulo = 'Nueva marca registrada';
            $detalle = 'Marca ' . $marca . ' agregada a la obra';
        } else {
            $fasePieza = FasePieza::with(['pieza.obra', 'fase'])->find($log->registro_id);

            if ($fasePieza) {
                $pieza = $fasePieza->pieza;
                $obra = $pieza?->obra;
                $marca = $pieza?->clave_marca_pieza ?? 'Sin marca';
                $nombreFase = $fasePieza->fase?->nombre ?? 'Fase';
            } else {
                $eliminado = true;
                $marca = 'Marca eliminada';
                $nombreFase = 'Fase eliminada';

                if (! empty($datos['pieza_id'])) {
                    $pieza = Pieza::with('obra')->find($datos['pieza_id']);
                    $obra = $pieza?->obra;
                    $marca = $pieza?->clave_marca_pieza ?? $marca;
                }
                if (! empty($datos['fase_id'])) {
                    $nombreFase = CatFase::find($datos['fase_id'])?->nombre ?? $nombreFase;
                }
            }

            $titulo = 'Nueva fase registrada';
            $detalle = 'Fase ' . $nombreFase . ' en la marca ' . $marca;
        }

        return [
            'id'                => $log->id,
            'tipo'              => $esPieza ? 'pieza_nueva' : 'fase_agregada',
            'titulo'            => $titulo,
            'detalle'           => $detalle,
            'estado'            => $eliminado ? 'registro_eliminado' : 'activo',
            'fecha_registro'    => FechaLocal::formato($log->fecha_hora, 'd/m/Y h:i A'),
            'actor_nombre'      => $log->usuario->nombre ?? 'Producción',
            'obra_nombre'       => $obra?->nombre_obra ?? $obra?->nombre ?? 'Obra eliminada',
            'clave_marca_pieza' => $marca,
            'url_pieza'         => ($pieza && Route::has('piezas.show'))
                ? route('piezas.show', $pieza->id)
                : null,
        ];
    }
}
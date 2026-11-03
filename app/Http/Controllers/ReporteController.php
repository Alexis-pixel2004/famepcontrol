<?php

namespace App\Http\Controllers;

use App\Models\CatFase;
use App\Models\FasePieza;
use App\Models\LogAuditoria;
use App\Models\ObservacionCalidad;
use App\Models\Obra;
use App\Models\Operario;
use App\Models\Pieza;
use App\Models\User;
use App\Support\FechaLocal;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    // Catálogo de reportes disponibles (el índice se arma de aquí).
    private const REPORTES = [
        [
            'clave'       => 'avance-obra',
            'ruta'        => 'reportes.avance-obra',
            'titulo'      => 'Avance por Obra',
            'descripcion' => 'Cumplimiento de fases por obra, marcas liberadas y semáforo de fechas.',
            'roles'       => ['Producción', 'Calidad'],
            'icono'       => 'chart',
        ],
        [
            'clave'       => 'calidad',
            'ruta'        => 'reportes.calidad',
            'titulo'      => 'Dictámenes de Calidad',
            'descripcion' => 'Resultados de inspección, tasas de rechazo/reproceso y dictámenes editados.',
            'roles'       => ['Producción', 'Calidad'],
            'icono'       => 'shield',
        ],
        [
            'clave'       => 'productividad',
            'ruta'        => 'reportes.productividad',
            'titulo'      => 'Productividad por Operario',
            'descripcion' => 'Fases cerradas por cada operario y especialidad en un periodo.',
            'roles'       => ['Producción', 'Calidad'],
            'icono'       => 'users',
        ],
        [
            'clave'       => 'hallazgos',
            'ruta'        => 'reportes.hallazgos',
            'titulo'      => 'Hallazgos por Fase y Obra',
            'descripcion' => 'Concentración de hallazgos y rechazos por etapa del proceso y por obra.',
            'roles'       => ['Producción', 'Calidad'],
            'icono'       => 'flag',
        ],
        [
            'clave'       => 'historial',
            'ruta'        => 'reportes.historial',
            'titulo'      => 'Historial de Marca',
            'descripcion' => 'Bitácora completa de una marca: fases, fechas, operarios, dictámenes y evidencias.',
            'roles'       => ['Producción', 'Calidad'],
            'icono'       => 'clock',
        ],
        [
            'clave'       => 'auditoria',
            'ruta'        => 'reportes.auditoria',
            'titulo'      => 'Bitácora de Auditoría',
            'descripcion' => 'Quién creó, editó o eliminó registros clave, con el valor anterior y el nuevo.',
            'roles'       => ['Producción'],
            'icono'       => 'lock',
        ],
    ];

    // Índice de reportes: tarjetas navegables.
    public function index()
    {
        $rol = auth()->user()->esCalidad() ? 'Calidad' : 'Producción';

        $reportes = collect(self::REPORTES)
            ->filter(fn (array $r) => in_array($rol, $r['roles'], true))
            ->values();

        return view('reportes.index', compact('reportes', 'rol'));
    }

    // Reporte: avance por obra (Producción).
    public function avanceObra(Request $request)
    {
        $filtros = $request->validate([
            'obra_id' => 'nullable|integer|exists:obras,id',
            'desde'   => 'nullable|date',
            'hasta'   => 'nullable|date',
        ]);

        $totalFases = CatFase::count();

        $consulta = Obra::query()
            ->when(! empty($filtros['obra_id']), fn ($q) => $q->whereKey($filtros['obra_id']))
            ->when(! empty($filtros['desde']), fn ($q) => $q->whereDate('fecha_inicio', '>=', $filtros['desde']))
            ->when(! empty($filtros['hasta']), fn ($q) => $q->whereDate('fecha_inicio', '<=', $filtros['hasta']))
            ->orderBy('codigo_obra');

        $obras = $consulta->with([
            'piezas.estatus',
            'piezas.fases.estatusFase',
            'piezas.fases.fase',
        ])->get();

        $hoy = FechaLocal::hoyUtc();

        $filas = $obras->map(function (Obra $obra) use ($totalFases, $hoy) {
            $piezas = $obra->piezas;

            $completadas = $piezas->sum(
                fn (Pieza $p) => $p->fases->filter(fn ($f) => (bool) $f->estatusFase?->es_completada)->count()
            );
            $esperadas = $piezas->count() * $totalFases;

            $porcentaje = $esperadas > 0 ? (int) round($completadas / $esperadas * 100) : 0;

            $liberadas = $piezas->filter(fn (Pieza $p) => $p->estatus?->nombre === 'Aprobada')->count();

            return [
                'obra'        => $obra,
                'marcas'      => $piezas->count(),
                'liberadas'   => $liberadas,
                'completadas' => $completadas,
                'esperadas'   => $esperadas,
                'porcentaje'  => $porcentaje,
                'semaforo'    => $this->semaforoFecha($obra, $porcentaje, $hoy),
            ];
        });

        $kpis = [
            'obras'       => $filas->count(),
            'marcas'      => $filas->sum('marcas'),
            'liberadas'   => $filas->sum('liberadas'),
            'completadas' => $filas->sum('completadas'),
            'esperadas'   => $filas->sum('esperadas'),
        ];
        $kpis['porcentaje'] = $kpis['esperadas'] > 0
            ? (int) round($kpis['completadas'] / $kpis['esperadas'] * 100)
            : 0;

        if ($request->query('formato') === 'csv') {
            return $this->descargarCsv(
                'reporte_avance_obra',
                ['Código', 'Obra', 'Cliente', 'Marcas', 'Liberadas', 'Fases completadas', 'Fases esperadas', '% Avance', 'Semáforo'],
                $filas->map(fn (array $f) => [
                    $f['obra']->codigo_obra,
                    $f['obra']->nombre_obra,
                    $f['obra']->cliente,
                    $f['marcas'],
                    $f['liberadas'],
                    $f['completadas'],
                    $f['esperadas'],
                    $f['porcentaje'] . '%',
                    $f['semaforo']['etiqueta'],
                ])
            );
        }

        $obrasFiltro = Obra::orderBy('codigo_obra')->get(['id', 'codigo_obra', 'nombre_obra']);

        return view('reportes.avance_obra', compact('filas', 'kpis', 'obrasFiltro', 'filtros'));
    }

    // Reporte: productividad por operario (fases cerradas en un periodo).
    public function productividad(Request $request)
    {
        $filtros = $request->validate([
            'operario_id' => 'nullable|integer|exists:operarios,id',
            'fase_id'     => 'nullable|integer|exists:cat_fases,id',
            'desde'       => 'nullable|date',
            'hasta'       => 'nullable|date',
        ]);

        // Solo cuentan fases realmente cerradas: con fecha_termino registrada.
        $fases = FasePieza::query()
            ->with(['operario.fasesHabilitadas', 'fase', 'pieza.obra'])
            ->whereNotNull('fecha_termino')
            ->whereNotNull('operario_id')
            ->when(! empty($filtros['operario_id']), fn ($q) => $q->where('operario_id', $filtros['operario_id']))
            ->when(! empty($filtros['fase_id']), fn ($q) => $q->where('fase_id', $filtros['fase_id']))
            ->when(! empty($filtros['desde']), fn ($q) => $q->where('fecha_termino', '>=', FechaLocal::inicioDiaUtc($filtros['desde'])))
            ->when(! empty($filtros['hasta']), fn ($q) => $q->where('fecha_termino', '<=', FechaLocal::finDiaUtc($filtros['hasta'])))
            ->orderByDesc('fecha_termino')
            ->get();

        // Agrupación por operario.
        $porOperario = $fases
            ->groupBy('operario_id')
            ->map(function ($grupo) {
                $operario = $grupo->first()->operario;
                $dias = $grupo->pluck('fecha_termino')->filter()
                    ->map(fn ($f) => FechaLocal::formato($f, 'Y-m-d'))->unique()->count();

                // Tiempo promedio de ciclo (inicio -> término) en días.
                $ciclos = $grupo->filter(fn ($f) => $f->fecha_inicio !== null && $f->fecha_termino !== null)
                    ->map(fn ($f) => $f->fecha_inicio->diffInDays($f->fecha_termino));

                return [
                    'operario'    => $operario,
                    'nombre'      => trim(($operario->nombre ?? '—') . ' ' . ($operario->apellido ?? '')),
                    'especialidad' => $operario?->especialidadDerivada() ?? '—',
                    'es_multifase' => $operario && $operario->fasesHabilitadas->count() > 1,
                    'fases'       => $grupo->count(),
                    'piezas'      => $grupo->pluck('pieza_id')->unique()->count(),
                    'dias'        => $dias,
                    'ciclo_promedio' => $ciclos->count() > 0 ? (int) round($ciclos->avg()) : null,
                    'por_fase'    => $grupo->groupBy(fn ($f) => $f->fase?->nombre ?? '—')
                        ->map->count()
                        ->sortDesc(),
                ];
            })
            ->sortByDesc('fases')
            ->values();

        // Agrupación por especialidad derivada (resumen).
        $porEspecialidad = $fases
            ->groupBy(fn ($f) => $f->operario?->especialidadDerivada() ?? '—')
            ->map(fn ($grupo, $esp) => [
                'especialidad' => $esp,
                'operarios'    => $grupo->pluck('operario_id')->unique()->count(),
                'fases'        => $grupo->count(),
            ])
            ->sortByDesc('fases')
            ->values();

        $kpis = [
            'operarios'    => $porOperario->count(),
            'fases'        => $fases->count(),
            'piezas'       => $fases->pluck('pieza_id')->unique()->count(),
            'fase_top'     => $fases->groupBy(fn ($f) => $f->fase?->nombre ?? '—')->map->count()->sortDesc()->keys()->first() ?? '—',
        ];

        if ($request->query('formato') === 'csv') {
            return $this->descargarCsv(
                'reporte_productividad',
                ['Operario', 'Clave', 'Especialidad', 'Tipo', 'Fases cerradas', 'Marcas distintas', 'Días con actividad', 'Ciclo promedio (días)', 'Desglose por fase'],
                $porOperario->map(fn (array $o) => [
                    $o['nombre'],
                    $o['operario']->clave_empleado ?? '—',
                    $o['especialidad'],
                    $o['es_multifase'] ? 'Multifase' : 'Una etapa',
                    $o['fases'],
                    $o['piezas'],
                    $o['dias'],
                    $o['ciclo_promedio'] ?? '—',
                    $o['por_fase']->map(fn ($n, $f) => "{$f}: {$n}")->implode(' | '),
                ])
            );
        }

        $operariosFiltro = Operario::orderBy('nombre')->get(['id', 'nombre', 'apellido', 'clave_empleado']);
        $fasesFiltro     = CatFase::orderBy('orden_secuencia')->get();

        return view('reportes.productividad', compact('porOperario', 'porEspecialidad', 'kpis', 'operariosFiltro', 'fasesFiltro', 'filtros'));
    }

    // Reporte: hallazgos concentrados por fase del proceso y por obra.
    public function hallazgos(Request $request)
    {
        $filtros = $request->validate([
            'obra_id' => 'nullable|integer|exists:obras,id',
            'fase_id' => 'nullable|integer|exists:cat_fases,id',
            'desde'   => 'nullable|date',
            'hasta'   => 'nullable|date',
        ]);

        $resultados = ['Rechazado', 'Enviado a Reproceso'];

        // "Hallazgo" = todo dictamen que NO fue aprobado (rechazo o reproceso).
        $base = ObservacionCalidad::query()
            ->with(['fasePieza.fase', 'fasePieza.pieza.obra', 'inspector'])
            ->when(! empty($filtros['obra_id']), fn ($q) => $q->whereHas(
                'fasePieza.pieza', fn ($p) => $p->where('obra_id', $filtros['obra_id'])
            ))
            ->when(! empty($filtros['fase_id']), fn ($q) => $q->whereHas(
                'fasePieza', fn ($f) => $f->where('fase_id', $filtros['fase_id'])
            ))
            ->when(! empty($filtros['desde']), fn ($q) => $q->where('created_at', '>=', FechaLocal::inicioDiaUtc($filtros['desde'])))
            ->when(! empty($filtros['hasta']), fn ($q) => $q->where('created_at', '<=', FechaLocal::finDiaUtc($filtros['hasta'])))
            ->orderByDesc('id');

        $todos        = $base->get();
        $hallazgos    = $todos->whereIn('resultado', $resultados);
        $total        = $todos->count();
        $totalHallazgos = $hallazgos->count();

        // Agrupación por fase del catálogo (todas las fases, aunque tengan 0).
        $fasesCat = CatFase::orderBy('orden_secuencia')->get();
        $porFase = $fasesCat->map(function (CatFase $fase) use ($todos, $resultados) {
            $delGrupo    = $todos->filter(fn ($d) => (int) $d->fasePieza?->fase_id === (int) $fase->id);
            $t           = $delGrupo->count();
            $malos       = $delGrupo->whereIn('resultado', $resultados)->count();

            return [
                'fase'        => $fase,
                'total'       => $t,
                'hallazgos'   => $malos,
                'rechazado'   => $delGrupo->where('resultado', 'Rechazado')->count(),
                'reproceso'   => $delGrupo->where('resultado', 'Enviado a Reproceso')->count(),
                'aprobado'    => $delGrupo->where('resultado', 'Aprobado')->count(),
                'pct_hallazgo' => $t > 0 ? (int) round($malos / $t * 100) : 0,
            ];
        });

        // Agrupación por obra.
        $porObra = $todos
            ->groupBy(fn ($d) => $d->fasePieza?->pieza?->obra?->codigo_obra ?? '—')
            ->map(function ($grupo, $codigo) use ($resultados) {
                $t     = $grupo->count();
                $malos = $grupo->whereIn('resultado', $resultados)->count();

                return [
                    'codigo'      => $codigo,
                    'nombre'      => $grupo->first()->fasePieza?->pieza?->obra?->nombre_obra ?? '—',
                    'total'       => $t,
                    'hallazgos'   => $malos,
                    'pct_hallazgo' => $t > 0 ? (int) round($malos / $t * 100) : 0,
                ];
            })
            ->sortByDesc('hallazgos')
            ->values();

        $kpis = [
            'total'      => $total,
            'hallazgos'  => $totalHallazgos,
            'pct'        => $total > 0 ? (int) round($totalHallazgos / $total * 100) : 0,
            'fase_critica' => $porFase->sortByDesc('hallazgos')->first()['fase']->nombre ?? '—',
        ];

        if ($request->query('formato') === 'csv') {
            return $this->descargarCsv(
                'reporte_hallazgos',
                ['Fase', 'Total dictámenes', 'Hallazgos', 'Rechazados', 'Reproceso', 'Aprobados', '% Hallazgo'],
                $porFase->map(fn (array $f) => [
                    $f['fase']->nombre,
                    $f['total'],
                    $f['hallazgos'],
                    $f['rechazado'],
                    $f['reproceso'],
                    $f['aprobado'],
                    $f['pct_hallazgo'] . '%',
                ])
            );
        }

        $obrasFiltro = Obra::orderBy('codigo_obra')->get(['id', 'codigo_obra', 'nombre_obra']);

        return view('reportes.hallazgos', compact('porFase', 'porObra', 'kpis', 'obrasFiltro', 'fasesCat', 'filtros'));
    }

    // Reporte: historial completo de una marca (bitácora).
    public function historial(Request $request)
    {
        $filtros = $request->validate([
            'obra_id'  => 'nullable|integer|exists:obras,id',
            'pieza_id' => 'nullable|integer|exists:piezas,id',
        ]);

        $obrasFiltro = Obra::orderBy('codigo_obra')->get(['id', 'codigo_obra', 'nombre_obra']);

        // Las piezas candidatas se acotan a la obra elegida (para el selector).
        $piezasFiltro = Pieza::query()
            ->with('obra')
            ->when(! empty($filtros['obra_id']), fn ($q) => $q->where('obra_id', $filtros['obra_id']))
            ->orderBy('clave_marca_pieza')
            ->get(['id', 'obra_id', 'clave_marca_pieza']);

        $pieza = null;
        $timeline = collect();

        if (! empty($filtros['pieza_id'])) {
            $pieza = Pieza::with([
                'obra',
                'tipoEstructura',
                'estatus',
                'fases.fase',
                'fases.estatusFase',
                'fases.operario',
                'fases.usuarioCaptura',
                'fases.evidencias',
                'fases.observaciones.inspector',
                'fases.observaciones.editadoPor',
                'fases.observaciones.evidencias',
            ])->find($filtros['pieza_id']);

            if ($pieza !== null) {
                $timeline = $this->construirTimeline($pieza);
            }
        }

        if ($request->query('formato') === 'csv') {
            return $this->descargarCsv(
                'historial_marca',
                ['Fecha', 'Evento', 'Fase', 'Detalle', 'Operario/Inspector', 'Resultado'],
                $timeline->map(fn (array $e) => [
                    $e['fecha'] ?? '—',
                    $e['tipo'],
                    $e['fase'],
                    $e['detalle'],
                    $e['responsable'],
                    $e['resultado'] ?? '—',
                ])
            );
        }

        return view('reportes.historial', compact('pieza', 'timeline', 'obrasFiltro', 'piezasFiltro', 'filtros'));
    }

    /**
     * Arma la línea de tiempo cronológica de una marca combinando los
     * eventos de sus fases: avance/cierre de fase y dictámenes de calidad.
     */
    private function construirTimeline(Pieza $pieza): \Illuminate\Support\Collection
    {
        $eventos = collect();

        foreach ($pieza->fases as $fase) {
            $nombreFase = $fase->fase?->nombre ?? '—';
            $operario   = $fase->operario
                ? trim($fase->operario->nombre . ' ' . $fase->operario->apellido)
                : '—';

            // Evento: inicio de fase (captura de avance).
            if ($fase->fecha_inicio !== null) {
                $eventos->push([
                    'orden'       => $fase->fecha_inicio->timestamp,
                    'fecha'       => FechaLocal::formato($fase->fecha_inicio, 'd/m/Y H:i'),
                    'tipo'        => 'Fase iniciada',
                    'fase'        => $nombreFase,
                    'detalle'     => 'Avance registrado por ' . ($fase->usuarioCaptura->nombre ?? '—') . '.',
                    'responsable' => $operario,
                    'resultado'   => null,
                    'color'       => 'slate',
                ]);
            }

            // Eventos: dictámenes de calidad de la fase.
            foreach ($fase->observaciones as $obs) {
                $eventos->push([
                    'orden'       => ($obs->fechaEdicion() ?? $obs->fechaInspeccion())?->timestamp ?? 0,
                    'fecha'       => FechaLocal::formato($obs->fechaInspeccion(), 'd/m/Y H:i'),
                    'tipo'        => 'Dictamen de calidad',
                    'fase'        => $nombreFase,
                    'detalle'     => $obs->descripcion,
                    'responsable' => $obs->inspector?->nombre ?? '—',
                    'resultado'   => $obs->resultado,
                    'editado'     => $obs->fueEditado(),
                    'editado_at'  => FechaLocal::formato($obs->fechaEdicion(), 'd/m/Y H:i'),
                    'editado_por' => $obs->editadoPor?->nombre,
                    'fotos'       => $obs->evidencias->count(),
                    'color'       => $obs->resultado === 'Aprobado'
                        ? 'emerald'
                        : ($obs->resultado === 'Rechazado' ? 'red' : 'amber'),
                ]);
            }

            // Evento: cierre de fase por Producción.
            if ($fase->fecha_termino !== null) {
                $eventos->push([
                    'orden'       => $fase->fecha_termino->timestamp,
                    'fecha'       => FechaLocal::formato($fase->fecha_termino, 'd/m/Y H:i'),
                    'tipo'        => 'Fase cerrada',
                    'fase'        => $nombreFase,
                    'detalle'     => 'Fase marcada como terminada por Producción.',
                    'responsable' => $operario,
                    'resultado'   => 'Completada',
                    'color'       => 'emerald',
                ]);
            }
        }

        return $eventos->sortBy('orden')->values();
    }

    // Reporte: bitácora de auditoría (solo Producción).
    public function auditoria(Request $request)
    {
        // Guard de rol: aunque la ruta está en jefatura, se revalida aquí.
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('reportes.index')
                ->with('error', 'Solo el Jefe de Producción puede ver la bitácora de auditoría.');
        }

        $filtros = $request->validate([
            'usuario_id'     => 'nullable|integer|exists:usuarios,id',
            'tabla_afectada' => 'nullable|string|in:' . implode(',', array_keys(\App\Services\AuditoriaService::ENTIDADES)),
            'accion'         => 'nullable|in:creado,editado,eliminado',
            'desde'          => 'nullable|date',
            'hasta'          => 'nullable|date',
        ]);

        $consulta = LogAuditoria::query()
            ->with('usuario')
            ->when(! empty($filtros['usuario_id']), fn ($q) => $q->where('usuario_id', $filtros['usuario_id']))
            ->when(! empty($filtros['tabla_afectada']), fn ($q) => $q->where('tabla_afectada', $filtros['tabla_afectada']))
            ->when(! empty($filtros['accion']), fn ($q) => $q->where('accion', $filtros['accion']))
            ->when(! empty($filtros['desde']), fn ($q) => $q->where('fecha_hora', '>=', FechaLocal::inicioDiaUtc($filtros['desde'])))
            ->when(! empty($filtros['hasta']), fn ($q) => $q->where('fecha_hora', '<=', FechaLocal::finDiaUtc($filtros['hasta'])))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id');

        $registros = $consulta->get();

        // KPIs por tipo de acción y por entidad.
        $kpis = [
            'total'      => $registros->count(),
            'creados'    => $registros->where('accion', 'creado')->count(),
            'editados'   => $registros->where('accion', 'editado')->count(),
            'eliminados' => $registros->where('accion', 'eliminado')->count(),
        ];

        $porEntidad = $registros
            ->groupBy('tabla_afectada')
            ->map(fn ($grupo, $tabla) => [
                'entidad' => \App\Services\AuditoriaService::ENTIDADES[$tabla] ?? $tabla,
                'total'   => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values();

        if ($request->query('formato') === 'csv') {
            return $this->descargarCsv(
                'bitacora_auditoria',
                ['Fecha', 'Usuario', 'Acción', 'Entidad', 'Registro', 'Detalle'],
                $registros->map(fn (LogAuditoria $l) => [
                    FechaLocal::formato($l->fecha_hora, 'd/m/Y H:i:s'),
                    $l->usuario?->nombre ?? 'Sistema',
                    $l->accionLegible(),
                    $l->entidad(),
                    $l->registro_id ?? '—',
                    $l->resumenCambios(),
                ])
            );
        }

        $usuariosFiltro = User::whereIn('rol_id', [1, 2])->orderBy('nombre')->get(['id', 'nombre']);

        return view('reportes.auditoria', compact('registros', 'kpis', 'porEntidad', 'usuariosFiltro', 'filtros'));
    }

    // Reporte: dictámenes de calidad.
    public function calidad(Request $request)
    {
        $filtros = $request->validate([
            'obra_id'   => 'nullable|integer|exists:obras,id',
            'fase_id'   => 'nullable|integer|exists:cat_fases,id',
            'resultado' => 'nullable|in:Aprobado,Rechazado,Enviado a Reproceso',
            'desde'     => 'nullable|date',
            'hasta'     => 'nullable|date',
        ]);

        $consulta = ObservacionCalidad::query()
            ->with(['inspector', 'editadoPor', 'evidencias', 'fasePieza.fase', 'fasePieza.pieza.obra'])
            ->when(! empty($filtros['obra_id']), fn ($q) => $q->whereHas(
                'fasePieza.pieza', fn ($p) => $p->where('obra_id', $filtros['obra_id'])
            ))
            ->when(! empty($filtros['fase_id']), fn ($q) => $q->whereHas(
                'fasePieza', fn ($f) => $f->where('fase_id', $filtros['fase_id'])
            ))
            ->when(! empty($filtros['resultado']), fn ($q) => $q->where('resultado', $filtros['resultado']))
            ->when(! empty($filtros['desde']), fn ($q) => $q->where('created_at', '>=', FechaLocal::inicioDiaUtc($filtros['desde'])))
            ->when(! empty($filtros['hasta']), fn ($q) => $q->where('created_at', '<=', FechaLocal::finDiaUtc($filtros['hasta'])))
            ->orderByDesc('id');

        $dictamenes = $consulta->get();

        $total = $dictamenes->count();
        $porResultado = [
            'Aprobado'            => $dictamenes->where('resultado', 'Aprobado')->count(),
            'Enviado a Reproceso' => $dictamenes->where('resultado', 'Enviado a Reproceso')->count(),
            'Rechazado'           => $dictamenes->where('resultado', 'Rechazado')->count(),
        ];

        $kpis = [
            'total'    => $total,
            'aprobado' => $porResultado['Aprobado'],
            'reproceso' => $porResultado['Enviado a Reproceso'],
            'rechazado' => $porResultado['Rechazado'],
            'editados' => $dictamenes->where('editado', true)->count(),
        ];
        $kpis['pct_aprobado'] = $total > 0 ? (int) round($kpis['aprobado'] / $total * 100) : 0;
        $kpis['pct_rechazo']  = $total > 0
            ? (int) round(($kpis['rechazado'] + $kpis['reproceso']) / $total * 100)
            : 0;

        // Agrupación por obra (tasa de rechazo/reproceso).
        $porObra = $dictamenes
            ->groupBy(fn ($d) => $d->fasePieza?->pieza?->obra?->codigo_obra ?? '—')
            ->map(function ($grupo, $codigo) {
                $t = $grupo->count();
                $malos = $grupo->filter(fn ($d) => in_array($d->resultado, ['Rechazado', 'Enviado a Reproceso'], true))->count();

                return [
                    'codigo'     => $codigo,
                    'nombre'     => $grupo->first()->fasePieza?->pieza?->obra?->nombre_obra ?? '—',
                    'total'      => $t,
                    'rechazo'    => $malos,
                    'pct_rechazo' => $t > 0 ? (int) round($malos / $t * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        if ($request->query('formato') === 'csv') {
            return $this->descargarCsv(
                'reporte_calidad',
                ['Fecha', 'Obra', 'Marca', 'Fase', 'Inspector', 'Resultado', 'Editado', 'Editado por', 'Descripción'],
                $dictamenes->map(fn ($d) => [
                    FechaLocal::formato($d->created_at, 'd/m/Y H:i'),
                    $d->fasePieza?->pieza?->obra?->codigo_obra ?? '—',
                    $d->fasePieza?->pieza?->clave_marca_pieza ?? '—',
                    $d->fasePieza?->fase?->nombre ?? '—',
                    $d->inspector?->nombre ?? '—',
                    $d->resultado,
                    $d->fueEditado() ? 'Sí' : 'No',
                    $d->editadoPor?->nombre ?? '—',
                    $d->descripcion,
                ])
            );
        }

        $obrasFiltro = Obra::orderBy('codigo_obra')->get(['id', 'codigo_obra', 'nombre_obra']);
        $fasesFiltro = CatFase::orderBy('orden_secuencia')->get();

        return view('reportes.calidad', compact('dictamenes', 'kpis', 'porObra', 'obrasFiltro', 'fasesFiltro', 'filtros'));
    }

    /**
     * Semáforo de cumplimiento: compara el avance real contra el tiempo
     * transcurrido del plazo de la obra (fecha_inicio -> fecha_compromiso).
     */
    private function semaforoFecha(Obra $obra, int $porcentaje, \Carbon\CarbonInterface $hoy): array
    {
        if ($porcentaje >= 100) {
            return ['clave' => 'ok', 'etiqueta' => 'Completada'];
        }

        $inicio     = $obra->fecha_inicio;
        $compromiso = $obra->fecha_compromiso;

        if ($inicio === null || $compromiso === null || $compromiso->lessThanOrEqualTo($inicio)) {
            return ['clave' => 'sin-fecha', 'etiqueta' => 'Sin fecha'];
        }

        if ($hoy->greaterThan($compromiso)) {
            return ['clave' => 'atrasada', 'etiqueta' => 'Atrasada'];
        }

        $totalDias   = $inicio->diffInDays($compromiso);
        $transcurridos = $inicio->diffInDays($hoy);
        $tiempoPct   = $totalDias > 0 ? (int) round(min($transcurridos / $totalDias, 1) * 100) : 0;

        // Se toleran 15 puntos de holgura entre lo esperado por tiempo y lo real.
        if ($porcentaje + 15 >= $tiempoPct) {
            return ['clave' => 'ok', 'etiqueta' => 'A tiempo'];
        }

        return ['clave' => 'riesgo', 'etiqueta' => 'En riesgo'];
    }

    /**
     * Descarga un CSV (UTF-8 con BOM para que Excel respete los acentos)
     * transmitido en streaming: no se acumula en memoria.
     */
    private function descargarCsv(string $nombre, array $cabeceras, iterable $filas)
    {
        $callback = function () use ($cabeceras, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($salida, $cabeceras);

            foreach ($filas as $fila) {
                fputcsv($salida, $fila);
            }

            fclose($salida);
        };

        return response()->streamDownload($callback, $nombre . '_' . FechaLocal::ahora('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte: Hallazgos por Fase y Obra - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('reportes.index') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">&larr; Reportes</a>
        </div>
        <span class="font-bold text-sm md:text-base">Hallazgos por Fase y Obra</span>
    </nav>

    <main class="p-4 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Hallazgos por Fase y Obra</h1>
            <p class="text-xs text-gray-500 mt-1">Un "hallazgo" es todo dictamen que no fue aprobado (rechazado o enviado a reproceso). Muestra en qué etapa y en qué obra se concentran los problemas.</p>
        </div>

        @php
            $filtrosExtra = view('reportes.partials.filtros_hallazgos', [
                'fasesCat' => $fasesCat,
                'filtros'  => $filtros,
            ])->render();
        @endphp
        @include('reportes.partials.filtros', ['action' => route('reportes.hallazgos'), 'filtrosExtra' => $filtrosExtra])

        {{-- KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Dictámenes</p>
                <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $kpis['total'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Hallazgos</p>
                <p class="text-2xl font-bold text-red-600 tabular-nums">{{ $kpis['hallazgos'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">% Hallazgo</p>
                <p class="text-2xl font-bold text-amber-600 tabular-nums">{{ $kpis['pct'] }}%</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Fase crítica</p>
                <p class="text-2xl font-bold text-slate-800">{{ $kpis['fase_critica'] }}</p>
            </div>
        </div>

        {{-- Gráficas --}}
        @include('reportes.partials.grafica', [
            'id'        => 'graficaHallazgosFase',
            'titulo'    => 'Composición por fase (aprobados, reproceso, rechazados)',
            'tipo'      => 'barras-apiladas',
            'etiquetas' => $porFase->map(fn ($f) => $f['fase']->nombre)->all(),
            'series'    => [
                ['nombre' => 'Aprobados',  'valores' => $porFase->map(fn ($f) => $f['aprobado'])->all(),  'color' => '#059669'],
                ['nombre' => 'Reproceso',  'valores' => $porFase->map(fn ($f) => $f['reproceso'])->all(),  'color' => '#d97706'],
                ['nombre' => 'Rechazados', 'valores' => $porFase->map(fn ($f) => $f['rechazado'])->all(),  'color' => '#dc2626'],
            ],
        ])

        {{-- Tabla por fase --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 pt-4">
                <h2 class="text-sm font-bold text-gray-700">Concentración por fase del proceso</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-gray-600 text-[11px] uppercase">
                        <tr>
                            <th scope="col" class="text-left px-4 py-3 font-semibold">Fase</th>
                            <th scope="col" class="text-center px-4 py-3 font-semibold">Dictámenes</th>
                            <th scope="col" class="text-center px-4 py-3 font-semibold">Aprobados</th>
                            <th scope="col" class="text-center px-4 py-3 font-semibold">Reproceso</th>
                            <th scope="col" class="text-center px-4 py-3 font-semibold">Rechazados</th>
                            <th scope="col" class="text-center px-4 py-3 font-semibold">Hallazgos</th>
                            <th scope="col" class="text-left px-4 py-3 font-semibold">% Hallazgo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($porFase as $f)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ $f['fase']->nombre }}</td>
                                <td class="px-4 py-3 text-center text-gray-700 tabular-nums">{{ $f['total'] }}</td>
                                <td class="px-4 py-3 text-center text-emerald-700 tabular-nums">{{ $f['aprobado'] }}</td>
                                <td class="px-4 py-3 text-center text-amber-700 tabular-nums">{{ $f['reproceso'] }}</td>
                                <td class="px-4 py-3 text-center text-red-700 tabular-nums">{{ $f['rechazado'] }}</td>
                                <td class="px-4 py-3 text-center font-bold text-gray-800 tabular-nums">{{ $f['hallazgos'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-28 h-2 rounded-full bg-gray-200 overflow-hidden" role="progressbar"
                                             aria-valuenow="{{ $f['pct_hallazgo'] }}" aria-valuemin="0" aria-valuemax="100"
                                             aria-label="Porcentaje de hallazgos en la fase {{ $f['fase']->nombre }}">
                                            <div class="h-full {{ $f['pct_hallazgo'] >= 50 ? 'bg-red-500' : ($f['pct_hallazgo'] > 0 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                                 style="width: {{ $f['pct_hallazgo'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-700 tabular-nums">{{ $f['pct_hallazgo'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-slate-500">
                                    No hay hallazgos que coincidan con los filtros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Ranking por obra --}}
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <h2 class="text-sm font-bold text-gray-700 mb-3">Obras con más hallazgos</h2>
            <div class="space-y-2">
                @forelse($porObra as $obra)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-56 truncate" title="{{ $obra['nombre'] }}"><span class="font-mono">{{ $obra['codigo'] }}</span> — {{ $obra['nombre'] }}</span>
                        <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden" role="progressbar"
                             aria-valuenow="{{ $obra['pct_hallazgo'] }}" aria-valuemin="0" aria-valuemax="100"
                             aria-label="Porcentaje de hallazgos de la obra {{ $obra['codigo'] }}">
                            <div class="h-full {{ $obra['pct_hallazgo'] >= 50 ? 'bg-red-500' : ($obra['pct_hallazgo'] > 0 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                 style="width: {{ $obra['pct_hallazgo'] }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 w-32 text-right tabular-nums">{{ $obra['hallazgos'] }}/{{ $obra['total'] }} ({{ $obra['pct_hallazgo'] }}%)</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">Sin dictámenes en el periodo/filtros seleccionados.</p>
                @endforelse
            </div>
        </div>
    </main>
</body>
</html>

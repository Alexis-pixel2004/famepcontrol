<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte: Productividad por Operario - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('reportes.index') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">&larr; Reportes</a>
        </div>
        <span class="font-bold text-sm md:text-base">Productividad por Operario</span>
    </nav>

    <main class="p-4 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Productividad por Operario</h1>
            <p class="text-xs text-gray-500 mt-1">Fases cerradas por operario (solo cuentan las fases con fecha de término registrada).</p>
        </div>

        @php
            $filtrosExtra = view('reportes.partials.filtros_productividad', [
                'operariosFiltro' => $operariosFiltro,
                'fasesFiltro'     => $fasesFiltro,
                'filtros'         => $filtros,
            ])->render();
        @endphp
        @include('reportes.partials.filtros', ['action' => route('reportes.productividad'), 'filtrosExtra' => $filtrosExtra])

        {{-- KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Operarios</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['operarios'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Fases cerradas</p>
                <p class="text-2xl font-bold text-emerald-600">{{ $kpis['fases'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Marcas distintas</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['piezas'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Fase más frecuente</p>
                <p class="text-2xl font-bold text-slate-800">{{ $kpis['fase_top'] }}</p>
            </div>
        </div>

        {{-- Gráficas --}}
        @php
            $topOperarios = $porOperario->take(8)->values();
        @endphp
        @include('reportes.partials.grafica', [
            'id'        => 'graficaProductividad',
            'titulo'    => 'Fases cerradas por operario (top 8)',
            'tipo'      => 'barras',
            'etiquetas' => $topOperarios->map(fn ($o) => $o['nombre'])->all(),
            'series'    => [[
                'nombre'  => 'Fases cerradas',
                'valores' => $topOperarios->map(fn ($o) => $o['fases'])->all(),
                'color'   => '#059669',
            ]],
        ])

        {{-- Tabla por operario --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-gray-600 text-[11px] uppercase">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Operario</th>
                            <th class="text-left px-4 py-3 font-semibold">Especialidad</th>
                            <th class="text-center px-4 py-3 font-semibold">Fases</th>
                            <th class="text-center px-4 py-3 font-semibold">Marcas</th>
                            <th class="text-center px-4 py-3 font-semibold">Días activo</th>
                            <th class="text-center px-4 py-3 font-semibold">Ciclo prom.</th>
                            <th class="text-left px-4 py-3 font-semibold">Desglose</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($porOperario as $op)
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-800">{{ $op['nombre'] }}</span>
                                    @if($op['es_multifase'])
                                        <span class="ml-1 px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-200">Multifase</span>
                                    @endif
                                    <span class="block text-[11px] text-gray-500">{{ $op['operario']->clave_empleado ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $op['especialidad'] }}</td>
                                <td class="px-4 py-3 text-center font-bold text-emerald-700">{{ $op['fases'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ $op['piezas'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $op['dias'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $op['ciclo_promedio'] !== null ? $op['ciclo_promedio'] . ' d' : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($op['por_fase'] as $nombreFase => $cantidad)
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ $nombreFase }}: {{ $cantidad }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No hay fases cerradas que coincidan con los filtros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Resumen por especialidad --}}
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <h2 class="text-sm font-bold text-gray-700 mb-3">Resumen por especialidad</h2>
            <div class="space-y-2">
                @forelse($porEspecialidad as $esp)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-48 truncate">{{ $esp['especialidad'] }}</span>
                        <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-slate-700"
                                 style="width: {{ $kpis['fases'] > 0 ? round($esp['fases'] / $kpis['fases'] * 100) : 0 }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 w-40 text-right">
                            {{ $esp['operarios'] }} operario(s) · {{ $esp['fases'] }} fases
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400">Sin datos en el periodo/filtros seleccionados.</p>
                @endforelse
            </div>
        </div>
    </main>
</body>
</html>

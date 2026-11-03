<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte: Avance por Obra - FAMEP</title>
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
        <span class="font-bold text-sm md:text-base">Avance por Obra</span>
    </nav>

    <main class="p-4 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Avance por Obra</h1>
            <p class="text-xs text-gray-500 mt-1">Cumplimiento de fases, marcas liberadas y semáforo de fechas.</p>
        </div>

        @include('reportes.partials.filtros', ['action' => route('reportes.avance-obra')])

        {{-- KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Obras</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['obras'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Marcas</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['marcas'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Liberadas</p>
                <p class="text-2xl font-bold text-emerald-600">{{ $kpis['liberadas'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Fases</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['completadas'] }}/{{ $kpis['esperadas'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Avance</p>
                <p class="text-2xl font-bold text-slate-800">{{ $kpis['porcentaje'] }}%</p>
            </div>
        </div>

        {{-- Gráficas --}}
        @php
            $topObras = $filas->sortByDesc('porcentaje')->take(8)->values();
            $conteoSemaforo = $filas->groupBy(fn ($f) => $f['semaforo']['etiqueta']);
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('reportes.partials.grafica', [
                'id'        => 'graficaAvance',
                'titulo'    => 'Avance por obra (top 8)',
                'tipo'      => 'barras',
                'etiquetas' => $topObras->map(fn ($f) => $f['obra']->codigo_obra)->all(),
                'series'    => [[
                    'nombre'  => '% Avance',
                    'valores' => $topObras->map(fn ($f) => $f['porcentaje'])->all(),
                    'color'   => '#059669',
                ]],
            ])

            @include('reportes.partials.grafica', [
                'id'        => 'graficaSemaforo',
                'titulo'    => 'Distribución del semáforo de fechas',
                'tipo'      => 'dona',
                'etiquetas' => ['A tiempo', 'En riesgo', 'Atrasada', 'Completada', 'Sin fecha'],
                'series'    => [[
                    'nombre'  => 'Obras',
                    'valores' => [
                        $conteoSemaforo->get('A tiempo', collect())->count(),
                        $conteoSemaforo->get('En riesgo', collect())->count(),
                        $conteoSemaforo->get('Atrasada', collect())->count(),
                        $conteoSemaforo->get('Completada', collect())->count(),
                        $conteoSemaforo->get('Sin fecha', collect())->count(),
                    ],
                    'color'   => '#0f172a',
                ]],
            ])
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-gray-600 text-[11px] uppercase">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Obra</th>
                            <th class="text-left px-4 py-3 font-semibold">Cliente</th>
                            <th class="text-center px-4 py-3 font-semibold">Marcas</th>
                            <th class="text-center px-4 py-3 font-semibold">Liberadas</th>
                            <th class="text-center px-4 py-3 font-semibold">Fases</th>
                            <th class="text-center px-4 py-3 font-semibold">Avance</th>
                            <th class="text-center px-4 py-3 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($filas as $fila)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-800">{{ $fila['obra']->codigo_obra }}</span>
                                    <span class="block text-[11px] text-gray-500">{{ $fila['obra']->nombre_obra }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $fila['obra']->cliente }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ $fila['marcas'] }}</td>
                                <td class="px-4 py-3 text-center text-emerald-700 font-semibold">{{ $fila['liberadas'] }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $fila['completadas'] }}/{{ $fila['esperadas'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 justify-center">
                                        <div class="w-20 h-2 rounded-full bg-gray-200 overflow-hidden">
                                            <div class="h-full bg-emerald-500" style="width: {{ $fila['porcentaje'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-700">{{ $fila['porcentaje'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border
                                        @if($fila['semaforo']['clave'] === 'ok') bg-emerald-100 text-emerald-800 border-emerald-200
                                        @elseif($fila['semaforo']['clave'] === 'riesgo') bg-amber-100 text-amber-800 border-amber-200
                                        @elseif($fila['semaforo']['clave'] === 'atrasada') bg-red-100 text-red-800 border-red-200
                                        @else bg-gray-100 text-gray-600 border-gray-200 @endif">
                                        {{ $fila['semaforo']['etiqueta'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No hay obras que coincidan con los filtros.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>

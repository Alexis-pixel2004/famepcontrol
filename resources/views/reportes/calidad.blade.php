<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte: Dictámenes de Calidad - FAMEP</title>
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
        <span class="font-bold text-sm md:text-base">Dictámenes de Calidad</span>
    </nav>

    <main class="p-4 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Dictámenes de Calidad</h1>
            <p class="text-xs text-gray-500 mt-1">Resultados de inspección, tasas de rechazo/reproceso y dictámenes editados.</p>
        </div>

        @php
            $filtrosExtra = view('reportes.partials.filtros_calidad', [
                'fasesFiltro' => $fasesFiltro,
                'filtros'     => $filtros,
            ])->render();
        @endphp
        @include('reportes.partials.filtros', ['action' => route('reportes.calidad'), 'filtrosExtra' => $filtrosExtra])

        {{-- KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Dictámenes</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['total'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Aprobados</p>
                <p class="text-2xl font-bold text-emerald-600">{{ $kpis['pct_aprobado'] }}%</p>
                <p class="text-[10px] text-gray-400">{{ $kpis['aprobado'] }} de {{ $kpis['total'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Reproceso</p>
                <p class="text-2xl font-bold text-amber-600">{{ $kpis['reproceso'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Rechazados</p>
                <p class="text-2xl font-bold text-red-600">{{ $kpis['rechazado'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Editados</p>
                <p class="text-2xl font-bold text-slate-700">{{ $kpis['editados'] }}</p>
            </div>
        </div>

        {{-- Gráficas --}}
        @php
            $topObrasCal = $porObra->take(8)->values();
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @include('reportes.partials.grafica', [
                'id'        => 'graficaResultados',
                'titulo'    => 'Distribución de resultados',
                'tipo'      => 'dona',
                'etiquetas' => ['Aprobado', 'En Reproceso', 'Rechazado'],
                'series'    => [[
                    'nombre'  => 'Dictámenes',
                    'valores' => [$kpis['aprobado'], $kpis['reproceso'], $kpis['rechazado']],
                    'color'   => '#0f172a',
                ]],
            ])

            @include('reportes.partials.grafica', [
                'id'        => 'graficaRechazoObra',
                'titulo'    => 'Tasa de rechazo/reproceso por obra (top 8)',
                'tipo'      => 'barras',
                'etiquetas' => $topObrasCal->map(fn ($f) => $f['codigo'])->all(),
                'series'    => [[
                    'nombre'  => '% Rechazo',
                    'valores' => $topObrasCal->map(fn ($f) => $f['pct_rechazo'])->all(),
                    'color'   => '#dc2626',
                ]],
            ])
        </div>

        {{-- Agrupación por obra --}}
        <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
            <h2 class="text-sm font-bold text-gray-700 mb-3">Tasa de rechazo/reproceso por obra</h2>
            <div class="space-y-2">
                @forelse($porObra as $fila)
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-56 truncate" title="{{ $fila['nombre'] }}">{{ $fila['codigo'] }} — {{ $fila['nombre'] }}</span>
                        <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full {{ $fila['pct_rechazo'] >= 50 ? 'bg-red-500' : ($fila['pct_rechazo'] > 0 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                 style="width: {{ $fila['pct_rechazo'] }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 w-28 text-right">{{ $fila['rechazo'] }}/{{ $fila['total'] }} ({{ $fila['pct_rechazo'] }}%)</span>
                    </div>
                @empty
                    <p class="text-xs text-gray-400">Sin dictámenes en el periodo/filtros seleccionados.</p>
                @endforelse
            </div>
        </div>

        {{-- Tabla de dictámenes --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-gray-600 text-[11px] uppercase">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Fecha</th>
                            <th class="text-left px-4 py-3 font-semibold">Obra / Marca</th>
                            <th class="text-left px-4 py-3 font-semibold">Fase</th>
                            <th class="text-left px-4 py-3 font-semibold">Inspector</th>
                            <th class="text-center px-4 py-3 font-semibold">Resultado</th>
                            <th class="text-left px-4 py-3 font-semibold">Observación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($dictamenes as $d)
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ \App\Support\FechaLocal::formato($d->created_at, 'd/m/Y H:i') }}
                                    @if($d->fueEditado())
                                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                                              title="Editado por {{ $d->editadoPor?->nombre ?? 'Calidad' }}">
                                            Editado {{ \App\Support\FechaLocal::formato($d->fechaEdicion(), 'd/m/Y H:i') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-800">{{ $d->fasePieza?->pieza?->obra?->codigo_obra ?? '—' }}</span>
                                    <span class="block text-[11px] text-gray-500">{{ $d->fasePieza?->pieza?->clave_marca_pieza ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $d->fasePieza?->fase?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $d->inspector?->nombre ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold
                                        @if($d->resultado === 'Aprobado') bg-emerald-100 text-emerald-800 border border-emerald-200
                                        @elseif($d->resultado === 'Enviado a Reproceso') bg-amber-100 text-amber-800 border border-amber-200
                                        @elseif($d->resultado === 'Rechazado') bg-red-100 text-red-800 border border-red-200
                                        @else bg-gray-100 text-gray-700 border border-gray-200 @endif">
                                        {{ $d->resultado === 'Enviado a Reproceso' ? 'En Reproceso' : $d->resultado }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-md">
                                    <span class="italic">"{{ $d->descripcion }}"</span>
                                    @if($d->evidencias->count() > 0)
                                        <span class="block text-[10px] text-gray-400 mt-1">{{ $d->evidencias->count() }} foto(s)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No hay dictámenes que coincidan con los filtros.
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

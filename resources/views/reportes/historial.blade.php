<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte: Historial de Marca - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('reportes.index') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">&larr; Reportes</a>
        </div>
        <span class="font-bold text-sm md:text-base">Historial de Marca</span>
    </nav>

    <main class="p-4 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Historial de Marca</h1>
            <p class="text-xs text-gray-500 mt-1">Bitácora cronológica completa de una marca: fases, avance, dictámenes, ediciones y evidencias.</p>
        </div>

        @php
            $filtrosExtra = view('reportes.partials.filtros_historial', [
                'piezasFiltro' => $piezasFiltro,
                'filtros'      => $filtros,
            ])->render();
        @endphp
        @include('reportes.partials.filtros', ['action' => route('reportes.historial'), 'filtrosExtra' => $filtrosExtra, 'ocultarFechas' => true])

        @if($pieza === null)
            <div class="bg-white p-10 rounded-xl border border-dashed border-gray-300 text-center">
                <p class="text-sm text-gray-500">Selecciona una marca para ver su historial completo.</p>
            </div>
        @else
            {{-- Encabezado de la marca --}}
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 font-mono">{{ $pieza->clave_marca_pieza }}</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            <span class="font-mono tabular-nums">{{ $pieza->obra->codigo_obra ?? '—' }}</span> — {{ $pieza->obra->nombre_obra ?? '—' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="px-2 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-200 font-semibold">
                            {{ $pieza->tipoEstructura->nombre ?? '—' }}
                        </span>
                        <span class="px-2 py-1 rounded-md bg-blue-50 text-blue-700 border border-blue-200 font-semibold">
                            {{ $pieza->estatus->nombre ?? '—' }}
                        </span>
                        <span class="px-2 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold tabular-nums">
                            {{ $pieza->porcentajeAvance() }}% avance
                        </span>
                    </div>
                </div>
            </div>

            {{-- Línea de tiempo --}}
            @forelse($timeline as $evento)
                @php
                    $colores = [
                        'slate'   => ['bg-slate-500',   'bg-slate-50',   'text-slate-700',   'border-slate-200'],
                        'emerald' => ['bg-emerald-500', 'bg-emerald-50', 'text-emerald-700', 'border-emerald-200'],
                        'amber'   => ['bg-amber-500',   'bg-amber-50',   'text-amber-700',   'border-amber-200'],
                        'red'     => ['bg-red-500',     'bg-red-50',     'text-red-700',     'border-red-200'],
                    ];
                    [$dot, $bg, $txt, $bd] = $colores[$evento['color'] ?? 'slate'];
                @endphp
                <div class="flex gap-3">
                    <div class="flex flex-col items-center pt-1.5">
                        <span class="h-3 w-3 rounded-full {{ $dot }} shrink-0"></span>
                        <span class="flex-1 w-px bg-gray-200"></span>
                    </div>
                    <div class="flex-1 pb-3">
                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $bg }} {{ $txt }} border {{ $bd }}">
                                        {{ $evento['tipo'] }}
                                    </span>
                                    <span class="text-xs font-semibold text-gray-700">{{ $evento['fase'] }}</span>
                                    @if(! empty($evento['editado']))
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200"
                                              title="Editado el {{ $evento['editado_at'] }}{{ $evento['editado_por'] ? ' por ' . $evento['editado_por'] : '' }}">
                                            Editado
                                        </span>
                                    @endif
                                </div>
                                <span class="text-xs text-slate-500 tabular-nums">{{ $evento['fecha'] }}</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-2 break-words">{{ $evento['detalle'] }}</p>
                            <div class="flex flex-wrap items-center gap-3 mt-2 text-[11px] text-gray-500">
                                <span>Responsable: <span class="font-medium text-gray-700">{{ $evento['responsable'] }}</span></span>
                                @if(! empty($evento['resultado']))
                                    <span>Resultado: <span class="font-medium text-gray-700">{{ $evento['resultado'] }}</span></span>
                                @endif
                                @if(! empty($evento['fotos']))
                                    <span class="tabular-nums">{{ $evento['fotos'] }} evidencia(s)</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white p-8 rounded-xl border border-dashed border-gray-300 text-center">
                    <p class="text-sm text-slate-500">Esta marca aún no tiene eventos registrados.</p>
                </div>
            @endforelse
        @endif
    </main>
</body>
</html>

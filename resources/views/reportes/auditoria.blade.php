<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte: Bitácora de Auditoría - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('reportes.index') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">&larr; Reportes</a>
        </div>
        <span class="font-bold text-sm md:text-base">Bitácora de Auditoría</span>
    </nav>

    <main class="p-4 max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Bitácora de Auditoría</h1>
            <p class="text-xs text-gray-500 mt-1">Registro de quién creó, editó o eliminó registros clave, con el valor anterior y el nuevo. Solo escrituras.</p>
        </div>

        @php
            $filtrosExtra = view('reportes.partials.filtros_auditoria', [
                'usuariosFiltro' => $usuariosFiltro,
                'filtros'        => $filtros,
            ])->render();
        @endphp
        @include('reportes.partials.filtros', ['action' => route('reportes.auditoria'), 'filtrosExtra' => $filtrosExtra])

        {{-- KPIs --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Movimientos</p>
                <p class="text-2xl font-bold text-gray-800">{{ $kpis['total'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Creaciones</p>
                <p class="text-2xl font-bold text-emerald-600">{{ $kpis['creados'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Ediciones</p>
                <p class="text-2xl font-bold text-amber-600">{{ $kpis['editados'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <p class="text-[11px] text-gray-500 uppercase font-semibold">Eliminaciones</p>
                <p class="text-2xl font-bold text-red-600">{{ $kpis['eliminados'] }}</p>
            </div>
        </div>

        {{-- Reparto por entidad --}}
        @if($porEntidad->isNotEmpty())
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                <h2 class="text-sm font-bold text-gray-700 mb-3">Movimientos por entidad</h2>
                <div class="space-y-2">
                    @foreach($porEntidad as $e)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-600 w-48 truncate">{{ $e['entidad'] }}</span>
                            <div class="flex-1 h-3 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-slate-700" style="width: {{ $kpis['total'] > 0 ? round($e['total'] / $kpis['total'] * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-xs font-semibold text-gray-700 w-10 text-right">{{ $e['total'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tabla de movimientos --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-gray-600 text-[11px] uppercase">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Fecha</th>
                            <th class="text-left px-4 py-3 font-semibold">Usuario</th>
                            <th class="text-center px-4 py-3 font-semibold">Acción</th>
                            <th class="text-left px-4 py-3 font-semibold">Entidad</th>
                            <th class="text-left px-4 py-3 font-semibold">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($registros as $log)
                            @php
                                $colores = [
                                    'emerald' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                    'amber'   => 'bg-amber-100 text-amber-800 border-amber-200',
                                    'red'     => 'bg-red-100 text-red-800 border-red-200',
                                    'slate'   => 'bg-gray-100 text-gray-700 border-gray-200',
                                ];
                                $clase = $colores[$log->colorAccion()] ?? $colores['slate'];
                            @endphp
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ \App\Support\FechaLocal::formato($log->fecha_hora, 'd/m/Y H:i:s') }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-800">{{ $log->usuario?->nombre ?? 'Sistema' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $clase }}">
                                        {{ $log->accionLegible() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $log->entidad() }}
                                    @if($log->registro_id)
                                        <span class="block text-[10px] text-gray-400">#{{ $log->registro_id }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-lg">{{ $log->resumenCambios() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-400">
                                    No hay movimientos que coincidan con los filtros.
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Obra - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('obras.index') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded transition">&larr; Volver</a>
        </div>
        <div class="flex items-center space-x-3">
            <span class="font-bold text-lg">FAMEP Control</span>
            <span class="hidden md:inline-block text-sm bg-slate-700 px-3 py-1 rounded-full">{{ Auth::user()->nombre }}</span>
        </div>
    </nav>

    <main class="p-4 md:p-6 max-w-5xl mx-auto space-y-6">
        <!-- Encabezado de la Obra -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-start mb-2">
                <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">{{ $obra->codigo_obra }}</span>
                <span class="text-xs px-2 py-1 rounded font-semibold {{ match($obra->estatus) { 'Activa' => 'bg-green-100 text-green-700', 'Pendiente' => 'bg-amber-100 text-amber-700', 'Finalizada' => 'bg-emerald-100 text-emerald-700', default => 'bg-gray-100 text-gray-600' } }}">{{ $obra->estatus }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $obra->nombre_obra }}</h1>
            <p class="text-sm text-gray-500 mt-1">Cliente: <strong>{{ $obra->cliente }}</strong></p>
            <div class="flex space-x-6 text-xs text-gray-500 mt-4 pt-3 border-t">
                <span>Inicio: <strong class="text-gray-700">{{ $obra->fecha_inicio }}</strong></span>
                <span>Compromiso: <strong class="text-gray-700">{{ $obra->fecha_compromiso }}</strong></span>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <!-- KPIs Específicos de la Obra -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Total Piezas</p>
                <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalPiezasObra }}</p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Piezas Liberadas</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ $piezasLiberadas }}</p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">En Reproceso</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">{{ $piezasReproceso }}</p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Pendientes</p>
                <p class="text-2xl font-extrabold text-gray-600 mt-1">{{ $piezasPendientes }}</p>
            </div>
        </div>

        <!-- RF-03: Avance acumulado de la obra -->
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-center mb-2">
                <h2 class="font-bold text-gray-800 text-sm">Avance acumulado de la obra</h2>
                <span class="text-lg font-extrabold {{ $avanceObra['porcentaje'] === 100 ? 'text-emerald-600' : 'text-blue-600' }}">
                    {{ $avanceObra['porcentaje'] }}%
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="{{ $avanceObra['porcentaje'] === 100 ? 'bg-emerald-600' : 'bg-blue-600' }} h-2.5 rounded-full"
                     style="width: {{ $avanceObra['porcentaje'] }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                {{ $avanceObra['completadas'] }} de {{ $avanceObra['esperadas'] }} fases completadas
                ({{ $totalFases }} fases por marca según catálogo)
            </p>
        </div>

        <!-- Formulario para Registrar Pieza / Marca -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-lg font-bold text-gray-800 mb-4">+ Agregar Pieza (Marca) a la Obra</h2>
            <form action="{{ route('piezas.store', $obra->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-700">Marca / Clave Pieza</label>
                    <input type="text" name="clave_marca_pieza" placeholder="Ej. C1, TB1" required class="w-full mt-1 p-2 text-sm border rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Tipo Estructura</label>
                    <select name="tipo_estructura_id" required class="w-full mt-1 p-2 text-sm border rounded-lg">
                        @foreach($tiposEstructura as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Peso Unitario (Kg)</label>
                    <input type="number" step="0.01" name="peso_kg" placeholder="0.00" required class="w-full mt-1 p-2 text-sm border rounded-lg">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Cantidad</label>
                    <input type="number" name="cantidad" value="1" min="1" required class="w-full mt-1 p-2 text-sm border rounded-lg">
                </div>
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        Guardar Pieza
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla / Listado de Piezas Registradas -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h2 class="font-bold text-gray-800">Piezas de la Obra ({{ $obra->piezas->count() }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-100 text-xs text-gray-700 uppercase">
                        <tr>
                            <th class="p-3">Marca</th>
                            <th class="p-3">Estructura</th>
                            <th class="p-3">Peso (Kg)</th>
                            <th class="p-3">Cantidad</th>
                            <th class="p-3">Avance</th>
                            <th class="p-3">Fase Actual</th>
                            <th class="p-3">Estatus</th>
                            @if(auth()->user()->esProduccion())
                                <th class="p-3">Acciones</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($obra->piezas as $pieza)
                            @php
                                $faseCur = $pieza->faseActual();
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-bold text-blue-600 hover:underline">
                                    <a href="{{ route('piezas.show', $pieza->id) }}">
                                    {{ $pieza->clave_marca_pieza }} &rarr;
                                    </a>
                                </td>
                                <td class="p-3">{{ $pieza->tipoEstructura->nombre ?? 'N/A' }}</td>
                                <td class="p-3">{{ number_format($pieza->peso_kg, 2) }} kg</td>
                                <td class="p-3">{{ $pieza->cantidad }}</td>
                                <td class="p-3 font-semibold {{ $pieza->porcentajeAvance() === 100 ? 'text-emerald-600' : 'text-blue-600' }}">
                                    {{ $pieza->porcentajeAvance() }}%
                                </td>
                                <td class="p-3">
                                    @if($pieza->sinFasesRegistradas())
                                        <span class="text-xs text-gray-400">Sin iniciar</span>
                                    @elseif($faseCur)
                                        <span class="text-xs font-semibold text-gray-700">{{ $faseCur->fase?->nombre }}</span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-bold
                                            @if($faseCur->estatusFase?->en_reproceso)
                                                bg-amber-100 text-amber-800
                                            @elseif($faseCur->estatusFase?->es_rechazada)
                                                bg-red-100 text-red-800
                                            @else
                                                bg-blue-100 text-blue-800
                                            @endif">
                                            {{ $faseCur->estatusFase?->nombre }}
                                        </span>
                                    @else
                                        <span class="text-xs font-semibold text-emerald-600">&check; Completada</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @php $nombreEstatus = $pieza->estatus->nombre ?? 'Pendiente'; @endphp
                                    <span class="text-xs px-2 py-1 rounded font-semibold
                                        @if($nombreEstatus === 'Aprobada')
                                            bg-emerald-100 text-emerald-800
                                        @elseif($nombreEstatus === 'Detenida')
                                            bg-red-100 text-red-800
                                        @elseif($nombreEstatus === 'En Revisión')
                                            bg-amber-100 text-amber-800
                                        @elseif($nombreEstatus === 'En Proceso')
                                            bg-blue-100 text-blue-800
                                        @else
                                            bg-gray-100 text-gray-700
                                        @endif">
                                        {{ $nombreEstatus }}
                                    </span>
                                </td>
                                @if(auth()->user()->esProduccion())
                                    <td class="p-3">
                                        <div class="flex items-center gap-3 text-xs">
                                            <a href="{{ route('piezas.edit', $pieza->id) }}" class="text-slate-600 font-semibold hover:underline">
                                                Editar
                                            </a>
                                            @if($pieza->sinFasesRegistradas())
                                                <form action="{{ route('piezas.destroy', $pieza->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar la pieza {{ $pieza->clave_marca_pieza }}? Esta acción no se puede deshacer.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 font-semibold hover:underline">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->esProduccion() ? 8 : 7 }}" class="p-4 text-center text-gray-400">No hay piezas registradas en esta obra.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
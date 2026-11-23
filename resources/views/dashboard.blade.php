<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FAMEP Control</title>
    <!-- Tailwind CSS -->
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <!-- Alpine.js -->
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">

    <x-sidebar-menu />

    <!-- Navbar Superior con Notificaciones Colgantes -->
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <span class="font-bold text-lg">FAMEP Panel General</span>
        </div>
        
        <div class="flex items-center space-x-3">
            <!-- Panel de notificaciones por rol -->
            @if (auth()->user()->esCalidad())
                <x-dropdown-actividad-taller />
            @elseif (auth()->user()->esProduccion())
                <x-dropdown-notificaciones :notificaciones="$notificacionesCalidad" />
            @endif

            <span class="hidden md:inline-block text-xs bg-slate-700 px-3 py-1 rounded-full">
                {{ Auth::user()->nombre ?? Auth::user()->name }}
            </span>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs bg-red-600 px-3 py-1 rounded hover:bg-red-700">
                    Salir
                </button>
            </form>
        </div>
    </nav>

    <!-- Contenido Principal del Dashboard -->
    <main class="p-4 md:p-6 max-w-6xl mx-auto space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">Panel General de Producción</h1>
            <p class="text-sm text-gray-500">Resumen de obras, avance del taller y marcas en proceso.</p>
        </div>

        <!-- Métricas Gerenciales / KPIs de Obras -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Obras Activas</p>
                <p class="text-2xl font-extrabold text-blue-600 mt-1">{{ $obrasActivas }}</p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Por Iniciar / Pendientes</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">{{ $obrasPendientes }}</p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Finalizadas</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ $obrasFinalizadas }}</p>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <p class="text-xs text-gray-500 font-semibold uppercase">Total de Obras</p>
                <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ $totalObras }}</p>
            </div>
        </div>

        <!-- RF-03: Cumplimiento General del Taller -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex justify-between items-center mb-3">
                <h2 class="font-bold text-gray-800 text-base">Cumplimiento General del Taller</h2>
                <span class="text-sm font-extrabold text-blue-600">{{ $avanceTaller['porcentaje'] }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3" role="progressbar"
                 aria-valuenow="{{ $avanceTaller['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Progreso general del taller">
                <div class="bg-blue-600 h-3 rounded-full transition-all motion-reduce:transition-none" style="width: {{ $avanceTaller['porcentaje'] }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                {{ $avanceTaller['completadas'] }} de {{ $avanceTaller['esperadas'] }} fases completadas
                ({{ $totalFases }} fases por marca según catálogo)
            </p>
        </div>

        <!-- RF-03: Cumplimiento por Obra y Fase Actual de cada Pieza -->
        <div>
            <h2 class="font-bold text-gray-800 text-base mb-3">Cumplimiento por Obra</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($obrasAvance as $item)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                                    {{ $item['obra']->codigo_obra }}
                                </span>
                                <h3 class="text-sm font-bold text-gray-800 mt-1">{{ $item['obra']->nombre_obra }}</h3>
                            </div>
                            <span class="text-2xl font-extrabold {{ $item['porcentaje'] === 100 ? 'text-emerald-600' : 'text-blue-600' }}">
                                {{ $item['porcentaje'] }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-3" role="progressbar"
                             aria-valuenow="{{ $item['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100"
                             aria-label="Progreso de la obra {{ $item['obra']->codigo_obra }}">
                            <div class="{{ $item['porcentaje'] === 100 ? 'bg-emerald-600' : 'bg-blue-600' }} h-2.5 rounded-full transition-all motion-reduce:transition-none"
                                 style="width: {{ $item['porcentaje'] }}%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mb-3">
                            {{ $item['completadas'] }}/{{ $item['esperadas'] }} fases&nbsp;·
                            {{ $item['total_marcas'] }} marcas&nbsp;·
                            {{ $item['liberadas'] }} liberadas
                        </p>

                        <div class="space-y-1.5">
                            @foreach($item['piezas'] as $detalle)
                                @php
                                    $pieza    = $detalle['pieza'];
                                    $faseCur  = $detalle['fase_actual'];
                                @endphp
                                <div class="flex flex-wrap justify-between items-center gap-y-1 text-xs py-1.5 border-t border-gray-100">
                                    <span class="font-semibold text-gray-700">
                                        <a href="{{ route('piezas.show', $pieza->id) }}" class="text-blue-600 hover:underline">
                                            {{ $pieza->clave_marca_pieza }}
                                        </a>
                                        @if($detalle['sin_inicio'])
                                            <span class="ml-1 text-slate-500">&middot; Sin iniciar</span>
                                        @elseif($faseCur)
                                            <span class="ml-1 text-gray-500">&rarr; {{ $faseCur->fase?->nombre }}</span>
                                            <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold
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
                                            <span class="ml-1 text-emerald-600">&check; Completada</span>
                                        @endif
                                    </span>
                                    <span class="text-gray-500">
                                        {{ $detalle['porcentaje'] }}%
                                        <span class="text-gray-300" aria-hidden="true">|</span>
                                        <span class="text-slate-500">{{ $pieza->peso_kg }} kg &times; {{ $pieza->cantidad }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500 text-center py-6 col-span-full">No hay obras registradas.</p>
                @endforelse
            </div>
        </div>

        <!-- Acceso Rápido y Obras Recientes -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold text-gray-800 text-base">Obras Recientes</h2>
                <a href="{{ route('obras.index') }}" class="text-xs font-semibold text-blue-600 hover:underline">
                    Ver todas &rarr;
                </a>
            </div>

            <div class="space-y-3">
                @forelse($obrasResumen as $obra)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border">
                        <div>
                            <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                                {{ $obra->codigo_obra }}
                            </span>
                            <h3 class="text-sm font-bold text-gray-800 mt-0.5">{{ $obra->nombre_obra }}</h3>
                            <p class="text-xs text-gray-500">{{ $obra->cliente }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-semibold text-gray-700 block">{{ $obra->piezas_count }} Marcas</span>
                            <a href="{{ route('obras.show', $obra->id) }}" class="text-xs text-blue-600 font-medium hover:underline">
                                Ver detalle
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500 text-center py-4">No hay obras registradas.</p>
                @endforelse
            </div>
        </div>

    </main>
</body>
</html>
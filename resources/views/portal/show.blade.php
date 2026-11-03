<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $obra->codigo_obra }} - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="bg-slate-800 text-white py-4 shadow">
        <div class="max-w-4xl mx-auto flex items-center justify-between px-4">
            <div>
                <p class="font-bold text-xl leading-tight">FAMEP</p>
                <p class="text-xs text-slate-400">Taller de estructuras metálicas</p>
            </div>
            <a href="{{ route('consulta.ver', $cliente->codigo_acceso) }}"
                class="text-sm text-slate-300 hover:text-white transition">← Volver a mis obras</a>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto w-full px-4 py-6 space-y-6">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-sm text-gray-500">{{ $obra->codigo_obra }}</p>
                    <h1 class="text-xl font-bold text-gray-800">{{ $obra->nombre_obra }}</h1>
                    <p class="text-sm text-gray-500 mt-1">Cliente: {{ $obra->cliente }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                    @if ($obra->estatus === 'Finalizada') bg-emerald-100 text-emerald-700
                    @elseif ($obra->estatus === 'Pendiente') bg-slate-100 text-slate-600
                    @else bg-blue-100 text-blue-700 @endif">
                    {{ $obra->estatus === 'Finalizada' ? 'Terminada' : ($obra->estatus === 'Pendiente' ? 'Por iniciar' : 'En fabricación') }}
                </span>
            </div>

            <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-400">Inicio</p>
                    <p class="font-medium text-gray-700">{{ $obra->fecha_inicio?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Entrega comprometida</p>
                    <p class="font-medium text-gray-700">{{ $obra->fecha_compromiso?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Marcas</p>
                    <p class="font-medium text-gray-700">{{ $marcas->count() }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Estado</p>
                    <p class="font-medium text-gray-700">
                        {{ $obra->estatus === 'Finalizada' ? 'Terminada' : ($obra->estatus === 'Pendiente' ? 'Por iniciar' : 'En fabricación') }}
                    </p>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-bold text-gray-800 mb-3">Marcas de la obra</h2>

            @forelse ($marcas as $marca)
                <div class="bg-white rounded-xl shadow-md p-5 mb-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-mono font-semibold text-gray-800">{{ $marca['clave'] }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $marca['tipo'] }} · {{ $marca['cantidad'] }} pza. · {{ $marca['peso_kg'] }} kg
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-gray-700">{{ $marca['porcentaje'] }}%</span>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                                @if ($marca['estatus_llano'] === 'Terminada') bg-emerald-100 text-emerald-700
                                @elseif ($marca['estatus_llano'] === 'En fabricación') bg-blue-100 text-blue-700
                                @elseif ($marca['estatus_llano'] === 'En espera de Calidad' || $marca['estatus_llano'] === 'Detenida') bg-amber-100 text-amber-700
                                @else bg-slate-100 text-slate-600 @endif">
                                {{ $marca['estatus_llano'] }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-3 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all
                            @if ($marca['porcentaje'] >= 100) bg-emerald-500
                            @elseif ($marca['porcentaje'] > 0) bg-blue-500
                            @else bg-gray-300 @endif"
                            style="width: {{ $marca['porcentaje'] }}%"></div>
                    </div>

                    <div class="mt-4 grid sm:grid-cols-3 gap-3">
                        @forelse ($marca['fases'] as $fase)
                            <div class="border rounded-lg p-3">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $fase['nombre'] }}</p>
                                <span class="mt-1 inline-block px-2 py-0.5 rounded-full text-xs font-semibold
                                    @if ($fase['estado_llano'] === 'Terminada' || $fase['estado_llano'] === 'Aprobada por Calidad') bg-emerald-100 text-emerald-700
                                    @elseif ($fase['estado_llano'] === 'En trabajo') bg-blue-100 text-blue-700
                                    @elseif ($fase['estado_llano'] === 'En corrección') bg-amber-100 text-amber-700
                                    @elseif ($fase['estado_llano'] === 'Rechazada') bg-red-100 text-red-700
                                    @else bg-slate-100 text-slate-600 @endif">
                                    {{ $fase['estado_llano'] }}
                                </span>
                                <p class="mt-2 text-xs text-gray-500">
                                    Inicio: <span class="font-medium text-gray-700">{{ $fase['fecha_inicio'] }}</span>
                                </p>
                                @if ($fase['estado_llano'] === 'Terminada')
                                    <p class="text-xs text-gray-500">
                                        Terminada: <span class="font-medium text-gray-700">{{ $fase['fecha_termino'] }}</span>
                                    </p>
                                @elseif ($fase['fecha_dictamen'] !== '—')
                                    <p class="text-xs text-gray-500">
                                        Revisada: <span class="font-medium text-gray-700">{{ $fase['fecha_dictamen'] }}</span>
                                    </p>
                                @endif
                            </div>
                        @empty
                            <div class="sm:col-span-3 text-sm text-gray-400">
                                Aún no se ha registrado trabajo en esta marca. Se iniciará pronto.
                            </div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <p class="text-gray-500">Esta obra todavía no tiene marcas registradas.</p>
                </div>
            @endforelse
        </div>

        <div class="bg-white rounded-xl shadow-md p-4 text-xs text-gray-500 space-y-1">
            <p class="font-semibold text-gray-600">Cómo leer este panel</p>
            <p>· <span class="font-medium">Por iniciar</span>: todavía no se ha trabajado en ello.</p>
            <p>· <span class="font-medium">En fabricación</span>: se está trabajando en el taller.</p>
            <p>· <span class="font-medium">En espera de Calidad</span>: está lista y en revisión de nuestros técnicos.</p>
            <p>· <span class="font-medium">Terminada</span>: trabajo terminado y aprobado.</p>
            <p class="mt-1">Cada marca pasa por tres etapas: <span class="font-medium">Armado</span>, <span class="font-medium">Soldadura</span> y <span class="font-medium">Pintura</span>.</p>
        </div>
    </main>

    <footer class="py-4 text-center text-xs text-gray-400">
        FAMEP – Control de Producción y Calidad
    </footer>
</body>
</html>
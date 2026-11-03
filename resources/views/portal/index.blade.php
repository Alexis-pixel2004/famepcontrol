<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Obras - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="bg-slate-800 text-white py-4 shadow">
        <div class="max-w-3xl mx-auto flex items-center justify-between px-4">
            <div>
                <p class="font-bold text-xl leading-tight">FAMEP</p>
                <p class="text-xs text-slate-400">Taller de estructuras metálicas</p>
            </div>
            <a href="{{ route('consulta.acceso') }}" class="text-sm text-slate-300 hover:text-white transition">Consultar otro código</a>
        </div>
    </header>

    <main class="flex-1 max-w-3xl mx-auto w-full px-4 py-6 space-y-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Hola, {{ $cliente->nombre }}</h1>
            <p class="text-sm text-gray-500">Estas son las obras que tenemos en el taller para ti.</p>
        </div>

        @if (session('success'))
            <div class="bg-emerald-100 text-emerald-800 p-3 rounded-lg text-sm">{{ session('success') }}</div>
        @endif

        @forelse ($obrasAvance as $obra)
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-mono text-sm text-gray-500">{{ $obra['codigo_obra'] }}</p>
                            <h2 class="text-lg font-bold text-gray-800">{{ $obra['nombre_obra'] }}</h2>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap
                            @if ($obra['estatus_llano'] === 'Terminada') bg-emerald-100 text-emerald-700
                            @elseif ($obra['estatus_llano'] === 'Por iniciar') bg-slate-100 text-slate-600
                            @else bg-blue-100 text-blue-700 @endif">
                            {{ $obra['estatus_llano'] }}
                        </span>
                    </div>

                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Avance de la obra</span>
                            <span class="font-semibold text-gray-700">{{ $obra['porcentaje'] }}%</span>
                        </div>
                        <div class="h-2.5 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all
                                @if ($obra['porcentaje'] >= 100) bg-emerald-500
                                @elseif ($obra['porcentaje'] > 0) bg-blue-500
                                @else bg-gray-300 @endif"
                                style="width: {{ $obra['porcentaje'] }}%"></div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400">Inicio</p>
                            <p class="font-medium text-gray-700">{{ $obra['fecha_inicio'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Entrega comprometida</p>
                            <p class="font-medium text-gray-700">{{ $obra['fecha_compromiso'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Marcas listas</p>
                            <p class="font-medium text-gray-700">{{ $obra['marcas_listas'] }} de {{ $obra['total_marcas'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">En qué estamos</p>
                            <p class="font-medium text-gray-700">{{ $obra['estatus_llano'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-5">
                    <a href="{{ route('consulta.obra', [$cliente->codigo_acceso, $obra['id']]) }}"
                        class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        Ver detalle por marca
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-md p-8 text-center">
                <p class="text-gray-500">Todavía no tenemos obras registradas a tu nombre.</p>
                <p class="text-sm text-gray-400 mt-1">Si acabas de hacer un pedido, comunícate con el taller.</p>
            </div>
        @endforelse
    </main>

    <footer class="py-4 text-center text-xs text-gray-400">
        FAMEP – Control de Producción y Calidad
    </footer>
</body>
</html>
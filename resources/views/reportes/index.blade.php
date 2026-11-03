<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('dashboard') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">&larr; Volver al Panel</a>
        </div>
        <span class="font-bold text-sm md:text-base">FAMEP Reportes</span>
    </nav>

    <main class="p-4 max-w-5xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Reportes</h1>
            <p class="text-xs text-gray-500 mt-1">Información de la plataforma filtrable y descargable. Rol activo: <strong>{{ $rol }}</strong>.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($reportes as $reporte)
                <a href="{{ route($reporte['ruta']) }}"
                   class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-emerald-300 transition block">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            @include('reportes.partials.icono', ['icono' => $reporte['icono']])
                        </span>
                        <div>
                            <h2 class="font-bold text-gray-800">{{ $reporte['titulo'] }}</h2>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $reporte['descripcion'] }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($reportes->isEmpty())
            <div class="bg-white border border-dashed border-gray-300 p-6 rounded-xl text-center text-xs text-gray-400">
                No hay reportes disponibles para tu rol.
            </div>
        @endif
    </main>
</body>
</html>

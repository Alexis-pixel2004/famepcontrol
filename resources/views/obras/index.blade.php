<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obras - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('dashboard') }}" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                &larr; Dashboard
            </a>
            <span class="font-bold text-lg">FAMEP Control</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="hidden md:inline-block text-sm bg-slate-700 px-3 py-1 rounded-full">
                {{ Auth::user()->nombre }}
            </span>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs bg-red-600 hover:bg-red-700 px-3 py-1 rounded transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Salir</button>
            </form>
        </div>
    </nav>

    <main class="p-4 md:p-6 max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Proyectos / Obras</h1>
            <a href="{{ route('obras.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                + Nueva Obra
            </a>
        </div>

        @if(session('success'))
            <div role="status" aria-live="polite" class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div role="alert" aria-live="assertive" class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($obras as $obra)
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 hover:border-blue-400 transition">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded font-mono">{{ $obra->codigo_obra }}</span>
                        <span class="text-xs px-2 py-1 rounded font-semibold {{ match($obra->estatus) { 'Activa' => 'bg-green-100 text-green-700', 'Pendiente' => 'bg-amber-100 text-amber-700', 'Finalizada' => 'bg-emerald-100 text-emerald-700', default => 'bg-gray-100 text-gray-600' } }}">
                            {{ $obra->estatus }}
                        </span>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-1">{{ $obra->nombre_obra }}</h2>
                    <p class="text-sm text-gray-500 mb-4">Cliente: {{ $obra->cliente }}</p>

                    <div class="flex justify-between items-center text-xs text-gray-500 pt-3 border-t">
                        <span>Piezas: <strong class="text-gray-800 tabular-nums">{{ $obra->piezas_count }}</strong></span>
                        <div class="flex items-center gap-3">
                            @if(auth()->user()->esProduccion())
                                <a href="{{ route('obras.edit', $obra->id) }}" aria-label="Editar obra {{ $obra->codigo_obra }}" class="text-slate-600 font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 rounded">
                                    Editar
                                </a>
                                @if($obra->estatus === 'Pendiente')
                                    <form action="{{ route('obras.destroy', $obra->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar la obra {{ $obra->codigo_obra }}? Esta acción no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" aria-label="Eliminar obra {{ $obra->codigo_obra }}" class="text-red-600 font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 rounded">
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            @endif
                            <a href="{{ route('obras.show', $obra->id) }}" aria-label="Ver detalles de la obra {{ $obra->codigo_obra }}" class="text-blue-600 font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 rounded">
                                Ver detalles &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-2 text-center py-8 text-gray-500">
                    No hay obras registradas actualmente.
                </div>
            @endforelse
        </div>
    </main>
</body>
</html>
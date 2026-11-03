<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operarios - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('dashboard') }}" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded transition">
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
                <button type="submit" class="text-xs bg-red-600 hover:bg-red-700 px-3 py-1 rounded transition">Salir</button>
            </form>
        </div>
    </nav>

    <main class="p-4 md:p-6 max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Operarios</h1>
                <p class="text-xs text-gray-500 mt-1">Cada operario tiene habilitadas las fases que puede ejecutar. Un operario multifase (ej. soldador/pintor) tendrá varias.</p>
            </div>
            @if(auth()->user()->esProduccion())
                <a href="{{ route('operarios.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                    + Nuevo Operario
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($operarios as $op)
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 hover:border-blue-400 transition">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">{{ $op->clave_empleado }}</span>
                        <span class="text-xs px-2 py-1 rounded font-semibold {{ $op->activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $op->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <h2 class="text-lg font-bold text-gray-800 mb-2">{{ $op->nombre }} {{ $op->apellido }}</h2>

                    <p class="text-[11px] text-gray-500 uppercase font-semibold mb-1">Especialidad (derivada)</p>
                    <p class="text-sm text-gray-700 mb-3">{{ $op->especialidadDerivada() }}</p>

                    <div class="mb-3">
                        <p class="text-[11px] text-gray-500 uppercase font-semibold mb-1">Fases habilitadas</p>
                        <div class="flex flex-wrap gap-1">
                            @forelse($op->fasesHabilitadas as $fase)
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $fase->nombre }}
                                </span>
                            @empty
                                <span class="text-[11px] text-amber-600">Sin fases configuradas (sin restricción)</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500 pt-3 border-t">
                        <span>Fases registradas: <strong class="text-gray-800">{{ $op->fases_count }}</strong></span>
                        <div class="flex items-center gap-3">
                            @if(auth()->user()->esProduccion())
                                <a href="{{ route('operarios.edit', $op->id) }}" class="text-slate-600 font-semibold hover:underline">
                                    Editar
                                </a>
                                @if($op->fases_count === 0)
                                    <form action="{{ route('operarios.destroy', $op->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar al operario {{ $op->nombre }} {{ $op->apellido }}? Esta acción no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 font-semibold hover:underline">
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-2 text-center py-8 text-gray-500">
                    No hay operarios registrados actualmente.
                </div>
            @endforelse
        </div>
    </main>
</body>
</html>

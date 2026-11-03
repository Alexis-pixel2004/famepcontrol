<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Operario - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen p-4 flex items-center justify-center">
    <x-sidebar-menu />
    <button type="button"
            @click="$store.layout.toggle()"
            aria-label="Abrir menú"
            class="fixed top-4 left-4 z-50 text-slate-500 hover:text-slate-800 focus:outline-none transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
    <div class="bg-white p-6 rounded-xl shadow-md w-full max-w-lg">
        <h1 class="text-xl font-bold text-gray-800 mb-4">Editar Operario</h1>

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('operarios.update', $operario->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Clave de Empleado</label>
                    <input type="text" name="clave_empleado" value="{{ old('clave_empleado', $operario->clave_empleado) }}" required class="w-full mt-1 p-2 border rounded-lg">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-2">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', $operario->activo) ? 'checked' : '' }} class="rounded border-gray-300">
                        Activo
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" name="nombre" value="{{ old('nombre', $operario->nombre) }}" required class="w-full mt-1 p-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Apellido</label>
                <input type="text" name="apellido" value="{{ old('apellido', $operario->apellido) }}" required class="w-full mt-1 p-2 border rounded-lg">
            </div>

            @php
                $fasesActuales = old('fases', $operario->fasesHabilitadas->pluck('id')->all());
            @endphp
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Fases que puede ejecutar
                    <span class="text-xs text-gray-400">(al menos una; la especialidad se deriva de estas)</span>
                </label>
                <div class="space-y-2 border border-gray-200 rounded-lg p-3">
                    @foreach($fases as $fase)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="fases[]" value="{{ $fase->id }}"
                                   {{ in_array($fase->id, $fasesActuales) ? 'checked' : '' }}
                                   class="rounded border-gray-300">
                            {{ $fase->nombre }}
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Un operario multifase marca varias; uno de una sola etapa marca solo la suya.</p>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('operarios.index') }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm">Guardar Cambios</button>
            </div>
        </form>
    </div>
</body>
</html>

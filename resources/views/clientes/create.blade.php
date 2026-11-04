<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Cliente - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen p-4 flex items-center justify-center">
    <x-sidebar-menu />
    <button type="button"
            @click="$store.layout.toggle()"
            aria-label="Abrir menú"
            class="fixed top-4 left-4 z-50 text-slate-500 hover:text-slate-800 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none transition">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
    <main class="bg-white p-6 rounded-xl shadow-md w-full max-w-lg">
        <h1 class="text-xl font-bold text-gray-800 mb-4">Registrar Cliente</h1>
        <p class="text-sm text-gray-500 mb-4">
            Se generará automáticamente un código de acceso (format. <span class="font-mono">FAMEP-XXXX</span>) que el cliente usará para ver el avance de sus obras.
        </p>

        @if($errors->any())
            <div role="alert" aria-live="assertive" class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('clientes.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre del cliente</label>
                <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" required class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">
                    Correo (opcional)
                    <span class="text-xs text-slate-500">Si no lo proporcionas, se genera uno automáticamente.</span>
                </label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="cliente@empresa.com" autocomplete="email" class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
            </div>

            <div role="group" aria-labelledby="obras-label">
                <p id="obras-label" class="block text-sm font-medium text-gray-700 mb-1">Obras que podrá consultar</p>
                <div class="max-h-56 overflow-y-auto border rounded-lg divide-y divide-gray-100">
                    @forelse($obras as $obra)
                        <label for="obra_{{ $obra->id }}" class="flex items-start gap-3 p-2 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" id="obra_{{ $obra->id }}" name="obras[]" value="{{ $obra->id }}"
                                {{ in_array($obra->id, old('obras', [])) ? 'checked' : '' }}
                                class="mt-1 rounded border-gray-300 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
                            <span>
                                <span class="font-mono text-xs font-semibold text-blue-600">{{ $obra->codigo_obra }}</span>
                                <span class="text-sm text-gray-700 ml-2">{{ $obra->nombre_obra }}</span>
                                <span class="text-xs text-slate-500 ml-1">({{ $obra->piezas_count }} piezas)</span>
                            </span>
                        </label>
                    @empty
                        <p class="p-3 text-sm text-gray-500">No hay obras registradas todavía.</p>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('clientes.index') }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none">Guardar Cliente</button>
            </div>
        </form>
    </main>
</body>
</html>

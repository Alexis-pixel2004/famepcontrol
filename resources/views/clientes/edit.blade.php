<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - FAMEP</title>
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
        <h1 class="text-xl font-bold text-gray-800 mb-4">Editar Cliente</h1>

        @if($errors->any())
            <div role="alert" aria-live="assertive" class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4 p-3 rounded-lg bg-slate-50 border border-slate-200">
            <p class="text-xs uppercase tracking-wide text-slate-500">Cliente</p>
            <p class="font-semibold text-gray-800">{{ $cliente->nombre }}</p>
            <p class="text-xs text-slate-500 mt-1">
                Código de acceso:
                <span class="font-mono font-semibold text-blue-600">{{ $cliente->codigo_acceso }}</span>
            </p>
        </div>

        <form action="{{ route('clientes.update', $cliente->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" id="activo" name="activo" value="1"
                       {{ old('activo', $cliente->activo) ? 'checked' : '' }}
                       aria-describedby="activo-ayuda"
                       class="mt-1 rounded border-gray-300 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
                <div>
                    <label for="activo" class="block text-sm font-medium text-gray-800">Código activo</label>
                    <p id="activo-ayuda" class="text-xs text-slate-500 mt-1">
                        Si se desactiva, el código dejará de dar acceso al portal de consulta. Puede reactivarse después.
                    </p>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('clientes.index') }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none">Guardar Estado</button>
            </div>
        </form>
    </main>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Obra - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data="{
    inicio: '{{ old('fecha_inicio') }}',
    get minCompromiso() {
        if (! this.inicio || this.inicio.length !== 10) return '';
        const [anio, mes, dia] = this.inicio.split('-').map(Number);
        return new Date(Date.UTC(anio, mes - 1, dia + 1)).toISOString().slice(0, 10);
    }
}" class="bg-gray-100 min-h-screen p-4 flex items-center justify-center">
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
        <h1 class="text-xl font-bold text-gray-800 mb-4">Registrar Nueva Obra</h1>

        @if($errors->any())
            <div role="alert" aria-live="assertive" class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('obras.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="codigo_obra" class="block text-sm font-medium text-gray-700">Código de Obra</label>
                <input type="text" id="codigo_obra" name="codigo_obra" placeholder="Ej. OB-2026-002" value="{{ old('codigo_obra') }}" required class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
            </div>

            <div>
                <label for="nombre_obra" class="block text-sm font-medium text-gray-700">Nombre de la Obra</label>
                <input type="text" id="nombre_obra" name="nombre_obra" value="{{ old('nombre_obra') }}" required class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
            </div>

            <div>
                <label for="cliente" class="block text-sm font-medium text-gray-700">Cliente</label>
                <input type="text" id="cliente" name="cliente" value="{{ old('cliente') }}" required class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
            </div>

            <div>
                <label for="cliente_usuario_id" class="block text-sm font-medium text-gray-700">
                    Cliente con acceso portal
                    <span class="text-xs text-slate-500">(opcional — para que vea el avance con su código)</span>
                </label>
                <select id="cliente_usuario_id" name="cliente_usuario_id" class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
                    <option value="">Sin acceso portal</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ old('cliente_usuario_id') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre }} ({{ $cliente->codigo_acceso ?? '' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="fecha_inicio" class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio') }}" x-model="inicio" required class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
                </div>
                <div>
                    <label for="fecha_compromiso" class="block text-sm font-medium text-gray-700">Fecha Compromiso</label>
                    <input type="date" id="fecha_compromiso" name="fecha_compromiso" value="{{ old('fecha_compromiso') }}" aria-describedby="ayuda_compromiso" :min="minCompromiso" required class="w-full mt-1 p-2 border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
                    <p id="ayuda_compromiso" class="mt-1 text-xs text-gray-500">Debe ser posterior a la fecha de inicio.</p>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('obras.index') }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none">Guardar Obra</button>
            </div>
        </form>
    </main>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Obra - FAMEP</title>
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
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-bold text-gray-800">Editar Obra</h1>
            <span class="text-xs px-2 py-1 rounded font-semibold {{ match($obra->estatus) { 'Activa' => 'bg-green-100 text-green-700', 'Pendiente' => 'bg-amber-100 text-amber-700', 'Finalizada' => 'bg-emerald-100 text-emerald-700', default => 'bg-gray-100 text-gray-600' } }}">
                {{ $obra->estatus }}
            </span>
        </div>

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('obras.update', $obra->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700">Código de Obra</label>
                <input type="text" name="codigo_obra" placeholder="Ej. OB-2026-002" value="{{ old('codigo_obra', $obra->codigo_obra) }}" required class="w-full mt-1 p-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nombre de la Obra</label>
                <input type="text" name="nombre_obra" value="{{ old('nombre_obra', $obra->nombre_obra) }}" required class="w-full mt-1 p-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Cliente</label>
                <input type="text" name="cliente" value="{{ old('cliente', $obra->cliente) }}" required class="w-full mt-1 p-2 border rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Cliente con acceso portal
                    <span class="text-xs text-gray-400">(opcional — para que vea el avance con su código)</span>
                </label>
                <select name="cliente_usuario_id" class="w-full mt-1 p-2 border rounded-lg">
                    <option value="">Sin acceso portal</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ (string) old('cliente_usuario_id', $obra->cliente_usuario_id) === (string) $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre }} ({{ $cliente->codigo_acceso ?? '' }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $obra->fecha_inicio?->format('Y-m-d')) }}" required class="w-full mt-1 p-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fecha Compromiso</label>
                    <input type="date" name="fecha_compromiso" value="{{ old('fecha_compromiso', $obra->fecha_compromiso?->format('Y-m-d')) }}" required class="w-full mt-1 p-2 border rounded-lg">
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('obras.index') }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm">Actualizar Obra</button>
            </div>
        </form>
    </div>
</body>
</html>

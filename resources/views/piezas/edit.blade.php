<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Marca {{ $pieza->clave_marca_pieza }} - FAMEP</title>
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
        @php $conAvance = ! $pieza->sinFasesRegistradas(); @endphp

        <div class="flex justify-between items-center mb-1">
            <h1 class="text-xl font-bold text-gray-800">Editar Pieza</h1>
            <span class="text-xs px-2 py-1 rounded font-semibold bg-gray-100 text-gray-700">
                {{ $pieza->estatus->nombre ?? 'Pendiente' }}
            </span>
        </div>
        <p class="text-xs text-gray-500 mb-4">Obra: <strong>{{ $pieza->obra->codigo_obra ?? '—' }}</strong> — {{ $pieza->obra->nombre_obra ?? '' }}</p>

        @if($errors->any())
            <div role="alert" aria-live="assertive" class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($conAvance)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-lg text-xs mb-4">
                Esta pieza ya tiene avance de producción. Solo se pueden editar el <strong>peso</strong> y la <strong>cantidad</strong>; la marca y el tipo de estructura quedan bloqueados.
            </div>
        @endif

        <form action="{{ route('piezas.update', $pieza->id) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700">Marca / Clave Pieza</label>
                @if($conAvance)
                    <input type="text" value="{{ $pieza->clave_marca_pieza }}" disabled class="w-full mt-1 p-2 border rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">
                @else
                    <input type="text" name="clave_marca_pieza" value="{{ old('clave_marca_pieza', $pieza->clave_marca_pieza) }}" required class="w-full mt-1 p-2 border rounded-lg">
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tipo Estructura</label>
                @if($conAvance)
                    <input type="text" value="{{ $pieza->tipoEstructura->nombre ?? 'N/A' }}" disabled class="w-full mt-1 p-2 border rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">
                @else
                    <select name="tipo_estructura_id" required class="w-full mt-1 p-2 border rounded-lg">
                        @foreach($tiposEstructura as $tipo)
                            <option value="{{ $tipo->id }}" {{ (string) old('tipo_estructura_id', $pieza->tipo_estructura_id) === (string) $tipo->id ? 'selected' : '' }}>
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Peso Unitario (Kg)</label>
                    <input type="number" step="0.01" name="peso_kg" value="{{ old('peso_kg', $pieza->peso_kg) }}" required class="w-full mt-1 p-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cantidad</label>
                    <input type="number" name="cantidad" value="{{ old('cantidad', $pieza->cantidad) }}" min="1" required class="w-full mt-1 p-2 border rounded-lg">
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('piezas.show', $pieza->id) }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm">Cancelar</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 text-sm">Actualizar Pieza</button>
            </div>
        </form>
    </main>
</body>
</html>

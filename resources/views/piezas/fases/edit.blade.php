<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar asignación de fase - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('piezas.show', $fasePieza->pieza_id) }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">&larr; Volver a la Marca</a>
        </div>
        <span class="font-bold text-sm md:text-base">FAMEP Producción</span>
    </nav>

    <main class="p-4 max-w-2xl mx-auto space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h1 class="text-xl font-bold text-gray-800 mb-1">Editar asignación de fase</h1>
            <p class="text-xs text-gray-500 mb-4">
                Obra: <strong>{{ $fasePieza->pieza->obra->codigo_obra ?? '—' }}</strong>
                — Marca: <strong>{{ $fasePieza->pieza->clave_marca_pieza ?? '—' }}</strong>
                — Fase: <strong>{{ $fasePieza->fase->nombre ?? '—' }}</strong>
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

            <form action="{{ route('fases.update', $fasePieza->id) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="operario_id" class="block text-sm font-medium text-gray-700">Operario asignado</label>
                    <select id="operario_id" name="operario_id" required
                            class="w-full mt-1 p-2 text-sm border rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500">
                        <option value="" disabled>Seleccione operario...</option>
                        @foreach($operarios as $op)
                            @php($habilitado = $op->puedeHacerFase($fasePieza->fase_id))
                            <option value="{{ $op->id }}"
                                    {{ (string) old('operario_id', $fasePieza->operario_id) === (string) $op->id ? 'selected' : '' }}
                                    {{ $habilitado ? '' : 'disabled' }}>
                                {{ $op->clave_empleado }} - {{ $op->nombre }} {{ $op->apellido }}
                                ({{ $op->especialidadDerivada }}){{ $habilitado ? '' : ' — no habilitado' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Solo se cambia el operario. La fase y su estatus se conservan.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                        Guardar asignación
                    </button>
                    <a href="{{ route('piezas.show', $fasePieza->pieza_id) }}"
                       class="text-center bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-gray-300 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>

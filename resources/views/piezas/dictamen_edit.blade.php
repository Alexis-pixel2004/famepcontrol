<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Dictamen - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('piezas.show', $fasePieza->pieza_id) }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">&larr; Volver a la Marca</a>
        </div>
        <span class="font-bold text-sm md:text-base">FAMEP Calidad</span>
    </nav>

    <main class="p-4 max-w-2xl mx-auto space-y-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h1 class="text-xl font-bold text-gray-800 mb-1">Editar Dictamen de Calidad</h1>
            <p class="text-xs text-gray-500 mb-4">
                Obra: <strong>{{ $fasePieza->pieza->obra->codigo_obra ?? '—' }}</strong>
                — Marca: <strong>{{ $fasePieza->pieza->clave_marca_pieza ?? '—' }}</strong>
                — Fase: <strong>{{ $fasePieza->fase->nombre ?? '—' }}</strong>
            </p>

            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                    <ul class="list-disc pl-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('calidad.update', $dictamen->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones / Notas de inspección</label>
                    <textarea name="descripcion" rows="4" required class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-emerald-500 focus:border-emerald-500">{{ old('descripcion', $dictamen->descripcion) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Resultado del Dictamen</label>
                    <select name="resultado" required class="w-full mt-1 p-2 text-sm border rounded-lg bg-white">
                        <option value="Aprobado" {{ old('resultado', $dictamen->resultado) === 'Aprobado' ? 'selected' : '' }}>Aprobar (enviar a Producción)</option>
                        <option value="Enviado a Reproceso" {{ old('resultado', $dictamen->resultado) === 'Enviado a Reproceso' ? 'selected' : '' }}>Enviar a Reproceso</option>
                        <option value="Rechazado" {{ old('resultado', $dictamen->resultado) === 'Rechazado' ? 'selected' : '' }}>Rechazar Marca</option>
                    </select>
                </div>

                @if($dictamen->evidencias && $dictamen->evidencias->count() > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Evidencias actuales <span class="text-xs text-gray-400">(marca las que quieras eliminar)</span></label>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                            @foreach($dictamen->evidencias as $foto)
                                <label class="relative block cursor-pointer group">
                                    <input type="checkbox" name="quitar_foto[]" value="{{ $foto->id }}" class="absolute top-1 left-1 z-10 h-4 w-4">
                                    <img src="{{ asset('storage/' . $foto->ruta_archivo) }}"
                                         alt="Evidencia"
                                         class="h-20 w-full object-cover rounded-lg border group-hover:opacity-80 transition">
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agregar nueva foto de evidencia <span class="text-xs text-gray-400">(opcional)</span></label>
                    <input type="file" name="foto" accept="image/*" capture="environment" class="text-sm text-gray-500">
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <a href="{{ route('piezas.show', $fasePieza->pieza_id) }}" class="px-4 py-2 border rounded-lg text-gray-600 hover:bg-gray-50 text-sm">Cancelar</a>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 text-sm">Actualizar Dictamen</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>

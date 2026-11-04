{{-- Barra de filtros reutilizable. Espera: $obrasFiltro (colección), $filtros (array), $action (ruta).
     El botón CSV conserva los filtros activos vía los campos del formulario. --}}
<form method="GET" action="{{ $action }}" class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-wrap items-end gap-3">
    @isset($obrasFiltro)
    <div class="flex-1 min-w-[180px]">
        <label for="obra_id" class="block text-[11px] text-gray-500 mb-1">Obra</label>
        <select id="obra_id" name="obra_id" class="w-full p-2 text-sm border rounded-lg bg-white focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
            <option value="">Todas</option>
            @foreach($obrasFiltro as $obra)
                <option value="{{ $obra->id }}" {{ (string) ($filtros['obra_id'] ?? '') === (string) $obra->id ? 'selected' : '' }}>
                    {{ $obra->codigo_obra }} — {{ $obra->nombre_obra }}
                </option>
            @endforeach
        </select>
    </div>
    @endisset

    @unless($ocultarFechas ?? false)
    <div>
        <label for="desde" class="block text-[11px] text-gray-500 mb-1">Desde</label>
        <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="p-2 text-sm border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
    </div>

    <div>
        <label for="hasta" class="block text-[11px] text-gray-500 mb-1">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="p-2 text-sm border rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
    </div>
    @endunless

    @isset($filtrosExtra)
        {!! $filtrosExtra !!}
    @endisset

    <div class="flex items-center gap-2">
        <button type="submit" class="bg-slate-800 text-white text-sm px-4 py-2 rounded-lg hover:bg-slate-700 font-semibold transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none">
            Filtrar
        </button>
        <a href="{{ $action }}" class="text-sm px-3 py-2 rounded-lg border text-gray-600 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Limpiar</a>
        <button type="submit" name="formato" value="csv" class="text-sm px-3 py-2 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 font-semibold focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none">
            Descargar CSV
        </button>
    </div>
</form>

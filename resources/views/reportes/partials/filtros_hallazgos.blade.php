{{-- Filtro extra del reporte de hallazgos: fase. --}}
<div>
    <label for="fase_id" class="block text-[11px] text-gray-500 mb-1">Fase</label>
    <select id="fase_id" name="fase_id" class="p-2 text-sm border rounded-lg bg-white focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
        <option value="">Todas</option>
        @foreach($fasesCat as $fase)
            <option value="{{ $fase->id }}" {{ (string) ($filtros['fase_id'] ?? '') === (string) $fase->id ? 'selected' : '' }}>
                {{ $fase->nombre }}
            </option>
        @endforeach
    </select>
</div>

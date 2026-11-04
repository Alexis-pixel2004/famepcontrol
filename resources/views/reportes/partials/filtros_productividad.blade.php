{{-- Filtros extra del reporte de productividad: operario y fase. --}}
<div>
    <label for="operario_id" class="block text-[11px] text-gray-500 mb-1">Operario</label>
    <select id="operario_id" name="operario_id" class="p-2 text-sm border rounded-lg bg-white focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
        <option value="">Todos</option>
        @foreach($operariosFiltro as $op)
            <option value="{{ $op->id }}" {{ (string) ($filtros['operario_id'] ?? '') === (string) $op->id ? 'selected' : '' }}>
                {{ $op->nombre }} {{ $op->apellido }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <label for="fase_id" class="block text-[11px] text-gray-500 mb-1">Fase</label>
    <select id="fase_id" name="fase_id" class="p-2 text-sm border rounded-lg bg-white focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
        <option value="">Todas</option>
        @foreach($fasesFiltro as $fase)
            <option value="{{ $fase->id }}" {{ (string) ($filtros['fase_id'] ?? '') === (string) $fase->id ? 'selected' : '' }}>
                {{ $fase->nombre }}
            </option>
        @endforeach
    </select>
</div>

{{-- Filtro extra del historial de marca: selector de marca (pieza). --}}
<div>
    <label class="block text-[11px] text-gray-500 mb-1">Marca</label>
    <select name="pieza_id" class="p-2 text-sm border rounded-lg bg-white min-w-[200px]">
        <option value="">Seleccione una marca…</option>
        @foreach($piezasFiltro as $p)
            <option value="{{ $p->id }}" {{ (string) ($filtros['pieza_id'] ?? '') === (string) $p->id ? 'selected' : '' }}>
                {{ $p->clave_marca_pieza }}@if($p->obra) · {{ $p->obra->codigo_obra }}@endif
            </option>
        @endforeach
    </select>
</div>

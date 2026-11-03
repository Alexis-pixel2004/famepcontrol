{{-- Filtros extra del reporte de calidad: fase y resultado. --}}
<div>
    <label class="block text-[11px] text-gray-500 mb-1">Fase</label>
    <select name="fase_id" class="p-2 text-sm border rounded-lg bg-white">
        <option value="">Todas</option>
        @foreach($fasesFiltro as $fase)
            <option value="{{ $fase->id }}" {{ (string) ($filtros['fase_id'] ?? '') === (string) $fase->id ? 'selected' : '' }}>
                {{ $fase->nombre }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-[11px] text-gray-500 mb-1">Resultado</label>
    <select name="resultado" class="p-2 text-sm border rounded-lg bg-white">
        <option value="">Todos</option>
        @foreach(['Aprobado' => 'Aprobado', 'Enviado a Reproceso' => 'En Reproceso', 'Rechazado' => 'Rechazado'] as $valor => $etiqueta)
            <option value="{{ $valor }}" {{ ($filtros['resultado'] ?? '') === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
        @endforeach
    </select>
</div>

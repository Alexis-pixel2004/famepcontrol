{{-- Filtros extra del reporte de calidad: fase y resultado. --}}
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

<div>
    <label for="resultado" class="block text-[11px] text-gray-500 mb-1">Resultado</label>
    <select id="resultado" name="resultado" class="p-2 text-sm border rounded-lg bg-white focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
        <option value="">Todos</option>
        @foreach(['Aprobado' => 'Aprobado', 'Enviado a Reproceso' => 'En Reproceso', 'Rechazado' => 'Rechazado'] as $valor => $etiqueta)
            <option value="{{ $valor }}" {{ ($filtros['resultado'] ?? '') === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
        @endforeach
    </select>
</div>

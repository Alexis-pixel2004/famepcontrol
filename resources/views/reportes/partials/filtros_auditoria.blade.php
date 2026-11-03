{{-- Filtros extra del reporte de auditoría: usuario, entidad, acción. --}}
<div>
    <label class="block text-[11px] text-gray-500 mb-1">Usuario</label>
    <select name="usuario_id" class="p-2 text-sm border rounded-lg bg-white">
        <option value="">Todos</option>
        @foreach($usuariosFiltro as $u)
            <option value="{{ $u->id }}" {{ (string) ($filtros['usuario_id'] ?? '') === (string) $u->id ? 'selected' : '' }}>
                {{ $u->nombre }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-[11px] text-gray-500 mb-1">Entidad</label>
    <select name="tabla_afectada" class="p-2 text-sm border rounded-lg bg-white">
        <option value="">Todas</option>
        @foreach(\App\Services\AuditoriaService::ENTIDADES as $tabla => $etiqueta)
            <option value="{{ $tabla }}" {{ ($filtros['tabla_afectada'] ?? '') === $tabla ? 'selected' : '' }}>
                {{ $etiqueta }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-[11px] text-gray-500 mb-1">Acción</label>
    <select name="accion" class="p-2 text-sm border rounded-lg bg-white">
        <option value="">Todas</option>
        <option value="creado" {{ ($filtros['accion'] ?? '') === 'creado' ? 'selected' : '' }}>Creación</option>
        <option value="editado" {{ ($filtros['accion'] ?? '') === 'editado' ? 'selected' : '' }}>Edición</option>
        <option value="eliminado" {{ ($filtros['accion'] ?? '') === 'eliminado' ? 'selected' : '' }}>Eliminación</option>
    </select>
</div>

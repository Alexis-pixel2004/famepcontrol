<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento Marca {{ $pieza->clave_marca_pieza }} - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('obras.show', $pieza->obra_id) }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">&larr; Volver a la Obra</a>
        </div>
        <span class="font-bold text-sm md:text-base">FAMEP Taller</span>
    </nav>

    <main class="p-4 max-w-5xl mx-auto space-y-6">
        <!-- Tarjeta de Info de Pieza -->
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">Obra: {{ $pieza->obra->codigo_obra }}</span>
            <h1 class="text-2xl font-bold text-gray-800 mt-2">Marca: {{ $pieza->clave_marca_pieza }}</h1>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-gray-600 mt-3 pt-3 border-t">
                <p>Estructura: <strong>{{ $pieza->tipoEstructura->nombre ?? 'N/A' }}</strong></p>
                <p>Cantidad: <strong>{{ $pieza->cantidad }} pza(s)</strong></p>
                <p>Peso total: <strong>{{ number_format($pieza->peso_kg * $pieza->cantidad, 2) }} kg</strong></p>
                <p>Estatus: <strong class="text-yellow-700">{{ $pieza->estatus->nombre }}</strong></p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs text-gray-500 mt-2 pt-2 border-t">
                @foreach($pieza->resumenFechas() as $rf)
                    <p>{{ $rf['etiqueta'] }}: <strong class="text-gray-700">{{ $rf['valor'] }}</strong></p>
                @endforeach
            </div>
        </div>

        <!-- Modal Emergente de ÉXITO -->
        @if(session('success'))
        <div id="modalNotificacionExito" role="dialog" aria-modal="true" aria-labelledby="modalNotificacionExitoTitulo"
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
            <div class="rounded-2xl bg-white px-8 py-10 shadow-2xl max-w-md w-full text-center">
                <div class="flex justify-center">
                    <div class="rounded-full bg-emerald-100 p-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-8 w-8 text-white" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                    </div>
                </div>
                <h3 id="modalNotificacionExitoTitulo" class="my-4 text-center text-2xl font-bold text-gray-800">¡Registro Exitoso!</h3>
                <p class="text-center font-normal text-gray-600 text-sm leading-relaxed">{{ session('success') }}</p>
                <button onclick="document.getElementById('modalNotificacionExito').remove()"
                        class="mx-auto mt-8 w-full rounded-xl bg-emerald-600 px-6 py-3 text-center text-sm font-semibold text-white hover:bg-emerald-700 transition duration-200 shadow-md">
                    Continuar
                </button>
            </div>
        </div>
        @endif

        <!-- Modal Emergente de ERROR / RESTRICCIÓN -->
        @if(session('error'))
        <div id="modalNotificacionError" role="dialog" aria-modal="true" aria-labelledby="modalNotificacionErrorTitulo"
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
            <div class="rounded-2xl bg-white px-8 py-10 shadow-2xl max-w-md w-full text-center">
                <div class="flex justify-center">
                    <div class="rounded-full bg-red-100 p-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-8 w-8 text-white" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <h3 id="modalNotificacionErrorTitulo" class="my-4 text-center text-2xl font-bold text-gray-800">Acción Bloqueada</h3>
                <p class="text-center font-normal text-gray-600 text-sm leading-relaxed">{{ session('error') }}</p>
                <button onclick="document.getElementById('modalNotificacionError').remove()"
                        class="mx-auto mt-8 w-full rounded-xl bg-red-600 px-6 py-3 text-center text-sm font-semibold text-white hover:bg-red-700 transition duration-200 shadow-md">
                    Entendido
                </button>
            </div>
        </div>
        @endif

        <!-- Formulario de Captura de Avance (solo Producción) -->
        @if(auth()->user()->esProduccion())
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-md font-bold text-gray-800 mb-3">+ Registrar Avance en Taller</h2>

            @php
                // Indexamos las fases de la pieza por su id para consultar fácilmente en el bucle
                $fasesRegistradas = $pieza->fases->keyBy('fase_id');
            @endphp

            <form action="{{ route('fases.store', $pieza->id) }}" method="POST" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                    <!-- Seleccionar Fase Operativa -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Fase Operativa</label>
                        <select id="selectFase" name="fase_id" required class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>Seleccione una fase...</option>
                            @foreach($fasesCat as $fase)
                                @php
                                    $faseP           = $fasesRegistradas->get($fase->id);
                                    $esCerrada       = (bool) $faseP?->estatusFase?->bloquea_captura;
                                    $listaParaCerrar = (bool) $faseP?->aprobadaPorCalidad();
                                    $enCurso         = $faseP?->estatusFase?->nombre === 'En Proceso';
                                    $operarioActualId = $faseP?->operario_id ?? '';
                                @endphp

                                <option value="{{ $fase->id }}"
                                        {{ $esCerrada ? 'disabled' : '' }}
                                        data-aprobado="{{ $listaParaCerrar ? 'true' : 'false' }}"
                                        data-en-curso="{{ $enCurso ? 'true' : 'false' }}"
                                        data-operario-id="{{ $operarioActualId }}">
                                    {{ $fase->nombre }} {{ $esCerrada ? '✓ (Cerrada)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Seleccionar Operario -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Operario Asignado</label>
                        <select id="selectOperario" name="operario_id" required class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>Seleccione operario...</option>
                            @foreach($operarios as $op)
                                <option value="{{ $op->id }}"
                                        data-fases-habilitadas="{{ $op->fasesHabilitadas->pluck('id')->implode(',') }}"
                                        data-texto-original="{{ $op->clave_empleado }} - {{ $op->nombre }} {{ $op->apellido }} ({{ $op->especialidadDerivada }})">
                                    {{ $op->clave_empleado }} - {{ $op->nombre }} {{ $op->apellido }} ({{ $op->especialidadDerivada }})
                                </option>
                            @endforeach
                        </select>
                        <p id="avisoOperario" class="hidden mt-1 text-[11px] text-amber-600"></p>
                    </div>

                    <!-- Seleccionar Estado de la Fase -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Estado de la Fase</label>
                        <select id="selectEstatusFase" name="estatus_fase_id" required class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="{{ $enProcesoId }}" id="optionEnProceso">En Proceso</option>
                            <option value="{{ $completadaId }}" id="optionCompletada">Completada</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition duration-150 mt-2">
                    Guardar Avance
                </button>
            </form>
        </div>
        @endif

        <!-- Historial de Procesos e Inspección de Calidad (LAYOUT EN GRID 2 COLUMNAS) -->
        <div class="space-y-4">
            <div x-data="{ actualizando: false }" class="flex flex-wrap items-center justify-between gap-3 border-b pb-2">
                <h2 class="text-lg font-bold text-gray-800">Historial de Procesos e Inspecciones</h2>

                <button type="button"
                        @click="actualizando = true; window.location.reload()"
                        :disabled="actualizando"
                        :aria-busy="actualizando ? 'true' : 'false'"
                        aria-label="Actualizar historial de procesos e inspecciones"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                    <span aria-hidden="true" class="text-base leading-none">↻</span>
                    <span x-text="actualizando ? 'Actualizando…' : 'Actualizar historial'">Actualizar historial</span>
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- COLUMNA IZQUIERDA: FASES ACTIVAS / EN PROCESO (2 Columnas en pantallas grandes) -->
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="text-sm font-bold text-blue-700 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 inline-block"></span>
                        Fases Activas / En Evaluacion
                    </h3>

                    @forelse($pieza->fases->reject(fn($f) => (bool) $f->estatusFase?->es_completada) as $hist)
                        <div class="p-4 bg-white rounded-xl border border-gray-200 shadow-sm space-y-3">
                            <div class="flex justify-between items-start text-xs">
                                <div>
                                    <p class="font-bold text-gray-800 text-sm">{{ $hist->fase->nombre }}</p>
                                    <p class="text-gray-600">Operario: {{ $hist->operario->nombre }} {{ $hist->operario->apellido }}</p>
                                    @include('piezas.partials.fecha_fase', ['hist' => $hist])
                                </div>
                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold shadow-sm
                                    @if($hist->estatusFase?->es_rechazada) bg-red-100 text-red-800 border border-red-300
                                    @elseif($hist->estatusFase?->en_reproceso) bg-amber-100 text-amber-800 border border-amber-300
                                    @elseif($hist->aprobadaPorCalidad()) bg-emerald-100 text-emerald-800 border border-emerald-300
                                    @else bg-blue-100 text-blue-800 border border-blue-300 @endif">
                                    {{ $hist->estatusFase?->nombre }}
                                </span>
                            </div>

                            @if(auth()->user()->esProduccion() && ! $hist->terminada() && ! $hist->estaCerrada())
                                <div class="pt-2 flex flex-wrap items-center gap-2 border-t border-gray-100">
                                    <a href="{{ route('fases.edit', $hist->id) }}"
                                       class="text-[11px] font-semibold text-blue-700 hover:text-blue-900 px-2 py-1 rounded border border-blue-200 hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-blue-500">
                                        Editar asignación
                                    </a>
                                    <form id="form-eliminar-asignacion-{{ $hist->id }}" action="{{ route('fases.destroy', $hist->id) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <x-confirmar-eliminacion
                                        :form-id="'form-eliminar-asignacion-' . $hist->id"
                                        :titulo="'Eliminar asignación de ' . $hist->fase->nombre"
                                        etiqueta="Eliminar asignación" />
                                </div>
                            @endif

                            <!-- Formulario de Inspección (Solo mientras la fase admita dictamen) -->
                            @if(auth()->user()->esCalidad() && $hist->admiteDictamen())
                                <form action="{{ route('calidad.store', $hist->id) }}" method="POST" enctype="multipart/form-data" class="pt-3 border-t space-y-3">
                                    @csrf
                                    <label class="block text-xs font-semibold text-gray-700">Evaluar Calidad de la Fase</label>

                                    <textarea name="descripcion" rows="2" placeholder="Observaciones o notas de inspección..." required class="w-full p-2 text-xs border rounded-lg focus:ring-emerald-500 focus:border-emerald-500"></textarea>

                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                            <label class="block text-[11px] text-gray-500 mb-1">Foto de evidencia (Opcional):</label>
                                            <input type="file" name="foto" accept="image/*" capture="environment" class="text-xs text-gray-500">
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <select name="resultado" required class="p-1.5 text-xs border rounded-lg bg-white">
                                                <option value="Aprobado">Aprobar (enviar a Producción)</option>
                                                <option value="Enviado a Reproceso">Enviar a Reproceso</option>
                                                <option value="Rechazado">Rechazar Marca</option>
                                            </select>

                                            <button type="submit" class="bg-emerald-600 text-white text-xs px-3 py-2 rounded-lg hover:bg-emerald-700 font-semibold transition">
                                                Dictaminar
                                            </button>
                                        </div>
                                    </div>

                                    <p class="text-[10px] text-gray-500">
                                        Solo <strong>Rechazar</strong> y <strong>Enviar a Reproceso</strong> generan alerta por WhatsApp. <strong>Aprobar</strong> no notifica.
                                    </p>
                                </form>
                            @elseif(auth()->user()->esCalidad())
                                <div class="pt-3 border-t">
                                    <p class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                        @if($hist->aprobadaPorCalidad())
                                            Fase aprobada por Calidad. Espera el cierre de Producción; no admite nuevos dictámenes.
                                        @else
                                            Marca rechazada. No admite nuevos dictámenes.
                                        @endif
                                    </p>
                                </div>
                            @endif

                            <!-- Evidencias Fotográficas -->
                            @include('piezas.partials.evidencias', ['hist' => $hist, 'variante' => 'activa'])

                            <!-- Observaciones Previas de la Fase Activa -->
                            @include('piezas.partials.observaciones', ['hist' => $hist])
                        </div>
                    @empty
                        <div class="bg-white border border-dashed border-gray-300 p-6 rounded-xl text-center text-xs text-slate-500">
                            No hay fases activas pendientes de evaluación.
                        </div>
                    @endforelse
                </div>


                <!-- COLUMNA DERECHA: FASES COMPLETADAS (1 Columna lateral - SOLO LECTURA, SIN FORMULARIO) -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                        Fases Finalizadas
                    </h3>

                    @forelse($pieza->fases->filter(fn($f) => (bool) $f->estatusFase?->es_completada) as $hist)
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 shadow-sm space-y-3 opacity-95">
                            <div class="flex justify-between items-start text-xs">
                                <div>
                                    <p class="font-bold text-slate-800 text-sm">{{ $hist->fase->nombre }}</p>
                                    <p class="text-gray-500 text-[11px]">Operario: {{ $hist->operario->nombre }} {{ $hist->operario->apellido }}</p>
                                    @include('piezas.partials.fecha_fase', ['hist' => $hist])
                                </div>
                                <span class="px-2 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    ✓ {{ $hist->estatusFase?->nombre }}
                                </span>
                            </div>

                            @if(auth()->user()->esCalidad())
                                <p class="text-[11px] text-slate-600 bg-white border border-slate-200 rounded-lg px-3 py-2">
                                    Cerrada por Producción. No admite nuevos dictámenes ni comentarios.
                                </p>
                            @endif

                            <!-- Galería de fotos (Solo Lectura) -->
                            @include('piezas.partials.evidencias', ['hist' => $hist, 'variante' => 'finalizada'])

                            <!-- Histórico de Observaciones / Dictamen (Solo Lectura) -->
                            @include('piezas.partials.observaciones', ['hist' => $hist])
                        </div>
                    @empty
                        <div class="bg-white border border-dashed border-gray-300 p-6 rounded-xl text-center text-xs text-slate-500">
                            Aún no hay fases completadas en esta marca.
                        </div>
                    @endforelse
                </div>

            </div>
        </div>
</main>
    <script src="{{ asset('js/fases-evaluacion.js') }}" defer></script>
</body>
</html>

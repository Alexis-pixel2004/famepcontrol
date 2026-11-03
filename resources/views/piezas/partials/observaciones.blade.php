{{-- Listado de dictámenes/inspecciones de una fase. Se usa tanto en la
     columna de fases activas como en la de fases finalizadas; ante un cambio
     aqui, se aplica a ambas secciones a la vez. Espera: $hist (FasePieza). --}}
@if($hist->observaciones && $hist->observaciones->count() > 0)
    <div class="mt-3 pt-3 border-t space-y-2">
        <p class="text-[11px] font-bold text-gray-700">Inspecciones realizadas:</p>

        @foreach($hist->observaciones as $obs)
            <div class="bg-white p-3 rounded-xl border border-gray-200 text-xs text-gray-700 space-y-2 shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="font-bold text-gray-800 block">{{ $obs->inspector->nombre ?? 'Inspector' }}</span>
                        <span class="text-[10px] text-gray-400 block mt-0.5">
                            {{ \App\Support\FechaLocal::formato($obs->fechaInspeccion(), 'd/m/Y h:i A') }}
                        </span>

                        @if($obs->fueEditado())
                            <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                                  title="Editado por {{ $obs->editadoPor->nombre ?? 'Calidad' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                                Editado {{ \App\Support\FechaLocal::formato($obs->fechaEdicion(), 'd/m/Y h:i A') }}
                            </span>
                        @endif
                    </div>

                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold
                        @if($obs->resultado === 'Aprobado') bg-emerald-100 text-emerald-800 border border-emerald-200
                        @elseif($obs->resultado === 'Enviado a Reproceso') bg-amber-100 text-amber-800 border border-amber-200
                        @elseif($obs->resultado === 'Rechazado') bg-red-100 text-red-800 border border-red-200
                        @else bg-gray-100 text-gray-700 @endif">
                        {{ $obs->resultado === 'Enviado a Reproceso' ? 'En Reproceso' : $obs->resultado }}
                    </span>
                </div>

                <p class="text-gray-600 leading-relaxed italic">"{{ $obs->descripcion }}"</p>

                @if(auth()->user()->esCalidad() && ! $hist->dictamenesInmutables())
                    <div class="flex items-center gap-3 pt-1">
                        <a href="{{ route('calidad.edit', $obs->id) }}" class="text-slate-600 font-semibold hover:underline">
                            Editar
                        </a>
                        <form action="{{ route('calidad.destroy', $obs->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este dictamen de calidad? Esta acción no se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 font-semibold hover:underline">
                                Eliminar
                            </button>
                        </form>
                    </div>
                @elseif(auth()->user()->esCalidad())
                    <p class="text-[10px] text-slate-500 pt-1 italic">
                        Dictamen en solo lectura: la fase ya fue cerrada por Producción.
                    </p>
                @endif

                @if($obs->evidencias && $obs->evidencias->count() > 0)
                    <div class="pt-2 border-t border-gray-100">
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-1.5">
                            @foreach($obs->evidencias as $foto)
                                <a href="{{ asset('storage/' . $foto->ruta_archivo) }}" target="_blank" class="block group overflow-hidden rounded-lg border bg-black/5">
                                    <img src="{{ asset('storage/' . $foto->ruta_archivo) }}"
                                         alt="Evidencia Inspección"
                                         class="h-16 w-full object-cover group-hover:scale-105 transition duration-150">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
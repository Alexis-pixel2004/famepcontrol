{{-- Galeria de evidencias adjuntas a la fase. $variante controla el tamano:
     'activa' (vista principal) o 'finalizada' (vista de solo lectura). --}}
@php
    $variante = $variante ?? 'activa';
    $envuelve   = $variante === 'activa' ? 'mt-3 pt-3 border-t' : 'pt-2 border-t border-slate-200';
    $titulo     = $variante === 'activa'
        ? '📷 Galería de Evidencias (' . $hist->evidencias->count() . ')'
        : 'Evidencias registradas:';
    $claseTitulo = $variante === 'activa' ? 'text-[11px] font-bold text-gray-700 mb-2' : 'text-[10px] font-bold text-slate-600 mb-1.5';
    $gridClase   = $variante === 'activa' ? 'grid grid-cols-3 gap-2' : 'grid grid-cols-3 gap-1.5';
    $claseLink   = $variante === 'activa' ? 'block group overflow-hidden rounded-lg border bg-black/5' : 'block overflow-hidden rounded border';
    $claseImg    = $variante === 'activa' ? 'h-20 w-full object-cover group-hover:opacity-85 transition' : 'h-14 w-full object-cover hover:opacity-85';
@endphp
@if($hist->evidencias && $hist->evidencias->count() > 0)
    <div class="{{ $envuelve }}">
        <p class="{{ $claseTitulo }}">{{ $titulo }}</p>
        <div class="{{ $gridClase }}">
            @foreach($hist->evidencias as $evidencia)
                <a href="{{ asset('storage/' . $evidencia->ruta_archivo) }}" target="_blank" rel="noopener noreferrer" class="{{ $claseLink }}">
                    <img src="{{ asset('storage/' . $evidencia->ruta_archivo) }}" alt="Evidencia fotográfica de la fase {{ $hist->fase->nombre }}" class="{{ $claseImg }}">
                </a>
            @endforeach
        </div>
    </div>
@endif
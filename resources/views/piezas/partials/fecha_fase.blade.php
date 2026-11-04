{{-- Fechas de la fase (Inicio y Dictamen). Markup identico en las secciones
     de fases activas y finalizadas; los valores ya vienen como Carbon por los
     casts del modelo, aqui solo se formatean. Espera: $hist (FasePieza). --}}
<p class="text-xs text-slate-500 tabular-nums">
    Inicio: {{ \App\Support\FechaLocal::formato($hist->fecha_inicio, 'd/m/Y') }}
    @if($hist->fecha_dictamen)
        | Dictamen: {{ \App\Support\FechaLocal::formato($hist->fecha_dictamen, 'd/m/Y') }}
    @endif
</p>
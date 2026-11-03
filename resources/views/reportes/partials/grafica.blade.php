{{--
    Partial reutilizable de gráfica (Chart.js local, sin CDN).
    Espera:
      $id       : id único del canvas (string)
      $titulo   : título visible (string)
      $tipo     : 'dona' | 'barras' | 'barras-apiladas'
      $etiquetas: array de labels (eje X o segmentos)
      $series   : array de ['nombre' => string, 'valores' => array, 'color' => hex]
      $vacio    : texto a mostrar si no hay datos (opcional)
--}}
@php
    $vacio = $vacio ?? 'Sin datos para graficar con los filtros actuales.';
    $hayDatos = collect($series)->contains(fn ($s) => array_sum(array_map('floatval', $s['valores'])) > 0);
@endphp

<div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
    <h2 class="text-sm font-bold text-gray-700 mb-3">{{ $titulo }}</h2>

    @if($hayDatos)
        <div class="relative" style="height: 280px;">
            <canvas id="{{ $id }}"></canvas>
        </div>
        <script type="application/json" id="{{ $id }}-datos">
            {!! json_encode([
                'tipo'      => $tipo,
                'etiquetas' => array_values($etiquetas),
                'series'    => array_values($series),
            ], JSON_UNESCAPED_UNICODE) !!}
        </script>
        <script>
            (function () {
                const cfg = JSON.parse(document.getElementById('{{ $id }}-datos').textContent);
                const ctx = document.getElementById('{{ $id }}');
                if (!ctx || typeof Chart === 'undefined') { return; }

                const paleta = ['#0f172a', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2'];

                const datasets = cfg.series.map((s, i) => ({
                    label: s.nombre,
                    data: s.valores.map(Number),
                    backgroundColor: s.color || paleta[i % paleta.length],
                    borderColor: '#ffffff',
                    borderWidth: cfg.tipo === 'dona' ? 2 : 0,
                    borderRadius: cfg.tipo === 'barras-apiladas' ? 3 : 4,
                }));

                const opciones = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: cfg.series.length > 1 || cfg.tipo === 'dona',
                            position: cfg.tipo === 'dona' ? 'right' : 'bottom',
                            labels: { boxWidth: 12, font: { size: 11 } },
                        },
                        tooltip: { enabled: true },
                    },
                };

                if (cfg.tipo === 'dona') {
                    opciones.cutout = '58%';
                } else {
                    opciones.scales = {
                        x: {
                            stacked: cfg.tipo === 'barras-apiladas',
                            grid: { display: false },
                            ticks: { font: { size: 10 } },
                        },
                        y: {
                            stacked: cfg.tipo === 'barras-apiladas',
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 10 } },
                        },
                    };
                }

                new Chart(ctx, {
                    type: cfg.tipo === 'dona' ? 'doughnut' : 'bar',
                    data: { labels: cfg.etiquetas, datasets: datasets },
                    options: opciones,
                });
            })();
        </script>
    @else
        <p class="text-xs text-gray-400">{{ $vacio }}</p>
    @endif
</div>

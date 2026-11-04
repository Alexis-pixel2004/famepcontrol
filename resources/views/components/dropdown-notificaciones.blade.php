<div class="relative" 
     x-data="{ 
        open: false, 
        notificaciones: [],
        ultimoIdVisto: localStorage.getItem('famep_ultimo_id_visto') || 0,
        
        // Calcula solo las no vistas
        get noLeidasCount() {
            return this.notificaciones.filter(n => n.id > this.ultimoIdVisto).length;
        },

        // Al dar clic en la campana, marca las actuales como vistas
        toggleMenu() {
            this.open = !this.open;
            if (this.open && this.notificaciones.length > 0) {
                const maxId = Math.max(...this.notificaciones.map(n => n.id));
                this.ultimoIdVisto = maxId;
                localStorage.setItem('famep_ultimo_id_visto', maxId);
            }
        },

        async cargarNotificaciones() {
            try {
                const res = await fetch('{{ route('api.notificaciones') }}');
                if (res.ok) {
                    this.notificaciones = await res.json();
                }
            } catch (error) {
                console.error('Error al actualizar notificaciones:', error);
            }
        }
     }" 
     x-init="cargarNotificaciones(); setInterval(() => cargarNotificaciones(), 15000)">

    <!-- Botón Campana -->
    <button @click="toggleMenu()" 
            aria-label="Notificaciones"
            :aria-expanded="open"
            aria-controls="panel-notificaciones"
            class="relative p-2 text-gray-300 hover:text-white hover:bg-slate-700/60 rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 flex items-center">
        
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        <!-- Contador: Solo aparece si hay elementos NO leídos -->
        <template x-if="noLeidasCount > 0">
            <span class="absolute top-1 right-1 flex h-4 w-4" aria-live="polite" aria-atomic="true">
                <span class="animate-ping motion-reduce:animate-none absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-4 w-4 bg-red-600 text-[9px] font-bold text-white items-center justify-center tabular-nums" 
                      x-text="noLeidasCount"></span>
            </span>
        </template>
    </button>

    <!-- Modal Dropdown Desplegable -->
    <div id="panel-notificaciones"
         x-show="open" 
         @click.away="open = false"
         @keydown.escape.window="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
         class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-50 overflow-hidden"
         style="display: none;">

        <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-xs uppercase tracking-wider flex items-center gap-2">
                🔔 Notificaciones de Calidad
            </h3>
            <span class="text-[10px] bg-slate-800 text-gray-300 font-medium px-2 py-0.5 rounded-full" 
                  x-text="`${notificaciones.length} Recientes`"></span>
        </div>

        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
            <template x-for="notif in notificaciones" :key="notif.id">
                <div class="p-3 hover:bg-slate-50 transition-colors flex flex-col gap-1 text-left"
                     :class="{
'border-l-4 border-l-amber-500 bg-amber-50/20': notif.resultado === 'Enviado a Reproceso',
                        'border-l-4 border-l-red-500 bg-red-50/20': notif.resultado === 'Rechazado',
                        'border-l-4 border-l-emerald-500 bg-emerald-50/20': notif.resultado === 'Aprobado'
                     }">
                      
                    <div class="flex items-center justify-between gap-2">
                        <span class="px-2 py-0.5 text-[9px] font-extrabold rounded uppercase"
                              :class="{
                                'bg-amber-100 text-amber-800': notif.resultado === 'Enviado a Reproceso',
                                'bg-red-100 text-red-800': notif.resultado === 'Rechazado',
                                'bg-emerald-100 text-emerald-800': notif.resultado === 'Aprobado'
                              }"
                              x-text="notif.resultado === 'Enviado a Reproceso' ? '⚠️ Reproceso' : (notif.resultado === 'Rechazado' ? '❌ Rechazada' : '✅ Aprobada')">
                        </span>
                        <span class="text-[10px] text-slate-500 tabular-nums" x-text="notif.fecha_registro"></span>
                    </div>

                    <div class="text-xs font-semibold text-gray-800">
                        Obra: <span class="text-gray-900 font-bold" x-text="notif.obra_nombre"></span>
                    </div>

                    <div class="text-xs text-gray-600">
                        Marca: <strong class="text-indigo-600" x-text="notif.clave_marca_pieza"></strong>
                    </div>

                    <p class="text-xs text-gray-600 italic line-clamp-2 mt-0.5 bg-white/80 p-1.5 rounded border border-gray-100" 
                       x-text="`&quot;${notif.descripcion}&quot;`"></p>

                    <div class="flex items-center justify-between mt-1 pt-1 border-t border-gray-100">
                        <span class="text-[10px] text-slate-500">
                            Por: <strong x-text="notif.inspector_nombre"></strong>
                        </span>
                        <template x-if="notif.url_pieza">
                            <a :href="notif.url_pieza" class="text-[11px] text-indigo-600 font-bold hover:underline rounded focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                Ver Marca &rarr;
                            </a>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="notificaciones.length === 0">
                <div class="p-6 text-center text-slate-500 text-xs">
                    No hay notificaciones recientes de calidad.
                </div>
            </template>
        </div>
    </div>
</div>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vincular WhatsApp - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
    <style>
        [x-cloak]{display:none!important}
    </style>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('dashboard') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">&larr; Volver al Panel</a>
        </div>
        <span class="font-bold text-sm md:text-base">FAMEP Control</span>
    </nav>

    <main class="p-4 max-w-2xl mx-auto space-y-6"
          x-data="{
              estado: 'cargando',
              imagen: '',
              instancia: '',
              mensaje: 'Consultando el estado de WhatsApp…',
              actualizado: '',
              cargando: true,
              async consultar(manual = false) {
                  if (manual) { this.cargando = true; }
                  try {
                      const res = await fetch('{{ route('whatsapp.qr.estado') }}', {
                          headers: { 'Accept': 'application/json' },
                          credentials: 'same-origin'
                      });
                      const datos = await res.json();
                      if (!res.ok && !datos.mensaje) {
                          throw new Error('Respuesta inesperada');
                      }
                      this.estado = datos.estado || 'error';
                      this.imagen = datos.imagen || '';
                      this.instancia = datos.instancia || '';
                      this.mensaje = datos.mensaje || 'No se pudo consultar el estado.';
                      this.actualizado = datos.actualizado || '';
                  } catch (error) {
                      this.estado = 'error';
                      this.imagen = '';
                      this.mensaje = 'No se pudo consultar el estado de WhatsApp.';
                  } finally {
                      this.cargando = false;
                  }
              }
          }"
          x-init="consultar(); setInterval(() => consultar(), 30000)">

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h1 class="text-xl font-bold text-slate-800">Vincular WhatsApp</h1>
            <p class="text-sm text-slate-600 mt-1">
                El código QR se obtiene directamente del servicio de WhatsApp y se renueva cada 30 segundos.
                No guarde ni comparta una captura del código.
            </p>

            <div class="mt-5 flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                <p class="text-sm text-slate-500" aria-live="polite" aria-atomic="true">
                    <span x-text="mensaje">Consultando el estado de WhatsApp…</span>
                    <span x-show="actualizado" x-cloak class="block text-xs" x-text="'Actualizado: ' + actualizado"></span>
                </p>
                <button type="button" @click="consultar(true)"
                        :aria-busy="cargando ? 'true' : 'false'"
                        class="shrink-0 bg-slate-700 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-slate-600 disabled:opacity-60 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                    Actualizar ahora
                </button>
            </div>
        </div>

        {{-- Cargando --}}
        <div x-show="cargando && estado === 'cargando'" x-cloak
             class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center text-slate-600">
            <p role="status" aria-live="polite">Consultando el estado de la instancia…</p>
        </div>

        {{-- Ya vinculada --}}
        <div x-show="!cargando && estado === 'vinculada'" x-cloak
             class="bg-emerald-50 border border-emerald-200 text-emerald-900 p-6 rounded-xl shadow-sm">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h2 class="font-bold">WhatsApp vinculado</h2>
                    <p class="text-sm mt-1">
                        La instancia <strong x-text="instancia">famep-wa</strong> está conectada.
                        No es necesario escanear un código. Si se desvincula, esta página mostrará el QR automáticamente.
                    </p>
                </div>
            </div>
        </div>

        {{-- QR disponible --}}
        <section x-show="!cargando && estado === 'qr'" x-cloak
                 class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h2 class="font-bold text-slate-800">Escanee el código desde su celular</h2>
            <div class="mt-4 flex flex-col items-center gap-4 md:flex-row md:items-start">
                <img :src="imagen"
                     alt="Código QR para vincular la instancia de WhatsApp famep-wa"
                     class="h-56 w-56 border border-gray-200 rounded-lg bg-white p-2" />
                <ol class="list-decimal list-inside space-y-2 text-sm text-slate-700">
                    <li>Abra WhatsApp en su celular.</li>
                    <li>Vaya a <strong>Ajustes → Dispositivos vinculados → Vincular un dispositivo</strong>.</li>
                    <li>Escanee el código mostrado.</li>
                    <li>Espere a que esta página indique “WhatsApp vinculado”.</li>
                </ol>
            </div>
            <p class="mt-4 text-xs text-slate-500">
                El código cambia cada pocos segundos; esta página se actualiza sola. Si aparece un error, use “Actualizar ahora”.
            </p>
        </section>

        {{-- Error --}}
        <div x-show="!cargando && estado === 'error'" x-cloak
             role="alert"
             class="bg-red-50 border border-red-200 text-red-900 p-6 rounded-xl shadow-sm">
            <h2 class="font-bold">No se pudo mostrar el código</h2>
            <p class="text-sm mt-1" x-text="mensaje"></p>
            <p class="text-sm mt-2">Revise que el servicio de WhatsApp esté encendido y vuelva a intentar.</p>
        </div>
    </main>
</body>
</html>

@props([
    'formId',
    'titulo',
    'mensaje' => 'Esta acción no se puede deshacer.',
    'etiqueta' => 'Eliminar',
])

<div class="inline" x-data="{ abierto: false }">
    <button type="button"
            x-ref="boton"
            @click="abierto = true; $nextTick(() => $refs.cancelar.focus())"
            :aria-expanded="abierto"
            aria-haspopup="dialog"
            aria-label="{{ $titulo }}"
            class="text-red-600 font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 rounded">
        {{ $etiqueta }}
    </button>

    <div x-show="abierto"
         style="display: none;"
         role="dialog"
         aria-modal="true"
         aria-labelledby="confirmar-{{ $formId }}-titulo"
         aria-describedby="confirmar-{{ $formId }}-mensaje"
         @keydown.escape.window="abierto = false"
         @click.self="abierto = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
        <div class="rounded-2xl bg-white px-8 py-8 shadow-2xl max-w-md w-full text-center">
            <div class="flex justify-center">
                <div class="rounded-full bg-red-100 p-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-red-500 p-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7 text-white" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                </div>
            </div>

            <h3 id="confirmar-{{ $formId }}-titulo" class="my-4 text-xl font-bold text-gray-800">
                {{ $titulo }}
            </h3>
            <p id="confirmar-{{ $formId }}-mensaje" class="text-sm leading-relaxed text-gray-600">
                {{ $mensaje }}
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <button type="button"
                        x-ref="cancelar"
                        @click="abierto = false; $nextTick(() => $refs.boton.focus())"
                        class="w-full rounded-xl bg-slate-100 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                    Cancelar
                </button>
                <button type="button"
                        @click="document.getElementById('{{ $formId }}').submit()"
                        class="w-full rounded-xl bg-red-600 px-6 py-3 text-sm font-semibold text-white hover:bg-red-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
                    Sí, eliminar
                </button>
            </div>
        </div>
    </div>
</div>

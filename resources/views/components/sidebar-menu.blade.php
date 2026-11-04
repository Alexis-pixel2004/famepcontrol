<style>
    [x-cloak]{display:none!important}
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('layout', {
            menuLateral: false,
            toggle() { this.menuLateral = !this.menuLateral; },
            cerrar()  { this.menuLateral = false; },
        });
    });
</script>

{{-- Fondo oscuro al abrir el menú --}}
<div x-cloak x-show="$store.layout.menuLateral" x-transition.opacity
     class="fixed inset-0 z-40 bg-black/50"
     @click="$store.layout.cerrar()" aria-hidden="true"></div>

{{-- Panel del menú lateral --}}
<aside id="menu-lateral" x-cloak x-show="$store.layout.menuLateral"
       role="dialog" aria-modal="true" aria-label="Menú principal"
       @keydown.escape.window="$store.layout.cerrar()"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="translate-x-0"
       x-transition:leave-end="-translate-x-full"
       class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-800 text-white flex flex-col shadow-xl">
    <div class="p-4 border-b border-slate-700 flex items-center justify-between">
        <div>
            <p class="font-bold text-lg leading-tight">FAMEP Control</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ Auth::user()->nombre ?? Auth::user()->name }}</p>
        </div>
        <button type="button" @click="$store.layout.cerrar()" aria-label="Cerrar menú" class="text-slate-400 hover:text-white rounded-lg focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 p-3 space-y-1">
        <a href="{{ route('dashboard') }}"
           {{ request()->routeIs('dashboard') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
            {{ request()->routeIs('dashboard') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            Dashboard
        </a>

        <a href="{{ route('obras.index') }}"
           {{ request()->routeIs('obras.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
            {{ request()->routeIs('obras.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
            </svg>
            Obras
        </a>

        <a href="{{ route('reportes.index') }}"
           {{ request()->routeIs('reportes.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
            {{ request()->routeIs('reportes.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </svg>
            Reportes
        </a>

        @if(auth()->user()->esProduccion())
        <a href="{{ route('operarios.index') }}"
           {{ request()->routeIs('operarios.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
            {{ request()->routeIs('operarios.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
            </svg>
            Operarios
        </a>
        @endif

        <a href="{{ route('clientes.index') }}"
           {{ request()->routeIs('clientes.*') ? 'aria-current="page"' : '' }}
           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
            {{ request()->routeIs('clientes.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            Clientes
        </a>
    </nav>

    <div class="p-3 border-t border-slate-700">
        <a href="{{ route('perfil.edit') }}"
           {{ request()->routeIs('perfil.*') ? 'aria-current="page"' : '' }}
           class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition mb-1 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2
            {{ request()->routeIs('perfil.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            Mi perfil
        </a>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-red-400 hover:bg-slate-700 hover:text-red-300 transition focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>
</aside>
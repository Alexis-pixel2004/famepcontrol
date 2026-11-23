<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('dashboard') }}" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                &larr; Dashboard
            </a>
            <span class="font-bold text-lg">FAMEP Control</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="hidden md:inline-block text-sm bg-slate-700 px-3 py-1 rounded-full">
                {{ Auth::user()->nombre }}
            </span>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs bg-red-600 hover:bg-red-700 px-3 py-1 rounded transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Salir</button>
            </form>
        </div>
    </nav>

    <main class="p-4 md:p-6 max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Clientes</h1>
                <p class="text-sm text-gray-500">Cuentas de acceso por código que ven el avance de sus obras.</p>
            </div>
            <a href="{{ route('clientes.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                + Nuevo Cliente
            </a>
        </div>

        @if(session('success'))
            <div role="status" aria-live="polite" class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div role="alert" class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nombre</th>
                            <th scope="col" class="px-4 py-3">Correo</th>
                            <th scope="col" class="px-4 py-3">Código de acceso</th>
                            <th scope="col" class="px-4 py-3 text-center">Obras asignadas</th>
                            <th scope="col" class="px-4 py-3 text-center">Estado</th>
                            <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($clientes as $cliente)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $cliente->nombre }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $cliente->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded tabular-nums">
                                        {{ $cliente->codigo_acceso }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center font-medium text-gray-700 tabular-nums">
                                    {{ $cliente->obras_asignadas_count }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($cliente->activo)
                                        <span class="inline-block px-2.5 py-1 rounded-md bg-green-100 text-green-700 text-xs font-bold">Activo</span>
                                    @else
                                        <span class="inline-block px-2.5 py-1 rounded-md bg-slate-200 text-slate-600 text-xs font-bold">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('clientes.edit', $cliente->id) }}" class="text-blue-600 font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 rounded" aria-label="Editar cliente {{ $cliente->nombre }}">
                                            Editar
                                        </a>
                                        <form id="form-eliminar-cliente-{{ $cliente->id }}" action="{{ route('clientes.destroy', $cliente->id) }}" method="POST" class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <x-confirmar-eliminacion
                                            :form-id="'form-eliminar-cliente-' . $cliente->id"
                                            :titulo="'Eliminar cliente ' . $cliente->nombre" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Aún no hay cuentas de clientes registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
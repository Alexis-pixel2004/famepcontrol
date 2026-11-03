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
            <a href="{{ route('dashboard') }}" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded transition">
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
                <button type="submit" class="text-xs bg-red-600 hover:bg-red-700 px-3 py-1 rounded transition">Salir</button>
            </form>
        </div>
    </nav>

    <main class="p-4 md:p-6 max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Clientes</h1>
                <p class="text-sm text-gray-500">Cuentas de acceso por código que ven el avance de sus obras.</p>
            </div>
            <a href="{{ route('clientes.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                + Nuevo Cliente
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Código de acceso</th>
                        <th class="px-4 py-3 text-center">Obras asignadas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clientes as $cliente)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $cliente->nombre }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $cliente->email }}</td>
                            <td class="px-4 py-3">
                                <span class="font-mono font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">
                                    {{ $cliente->codigo_acceso }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-gray-700">
                                {{ $cliente->obras_asignadas_count }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                Aún no hay cuentas de clientes registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
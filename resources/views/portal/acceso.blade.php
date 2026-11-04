<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAMEP - Consulta de Obra</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <header class="bg-slate-800 text-white py-4 shadow">
        <div class="max-w-xl mx-auto flex items-center justify-between px-4">
            <div>
                <p class="font-bold text-xl leading-tight">FAMEP</p>
                <p class="text-xs text-slate-400">Taller de estructuras metálicas</p>
            </div>
            <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition rounded focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">Iniciar sesión (personal)</a>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-md">
            <h1 class="text-xl font-bold text-gray-800 text-center">Consulta el avance de tu obra</h1>
            <p class="text-sm text-gray-500 text-center mt-1 mb-6">
                Ingresa el código que te entregó el taller para ver en tiempo real cómo va tu pedido.
            </p>

            @if (session('error'))
                <div role="status" aria-live="polite" class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('consulta.buscar') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700">Código de acceso</label>
                    <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}" required autofocus
                        placeholder="Ej. FAMEP-8X2Z" autocomplete="off"
                        class="w-full mt-1 p-3 border rounded-lg uppercase tracking-widest focus:ring-2 focus:ring-blue-500 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none">
                </div>

                <button type="submit"
                    class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Ver mi obra
                </button>
            </form>
        </div>
    </main>

    <footer class="py-4 text-center text-xs text-slate-500">
        FAMEP – Control de Producción y Calidad
    </footer>
</body>
</html>

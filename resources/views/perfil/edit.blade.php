<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - FAMEP</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>
</head>
<body x-data class="bg-gray-100 min-h-screen">
    <x-sidebar-menu />
    <nav class="bg-slate-800 text-white p-4 flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-3">
            <x-sidebar-toggle />
            <a href="{{ route('dashboard') }}" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded">&larr; Volver al Panel</a>
        </div>
        <span class="font-bold text-sm md:text-base">FAMEP Control</span>
    </nav>

    <main class="p-4 max-w-2xl mx-auto space-y-6">
        {{-- Datos del usuario (solo lectura) --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h1 class="text-xl font-bold text-gray-800 mb-4">Mi Perfil</h1>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <dt class="text-gray-500 font-medium">Nombre</dt>
                    <dd class="text-gray-800 font-semibold">{{ $usuario->nombre }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <dt class="text-gray-500 font-medium">Correo</dt>
                    <dd class="text-gray-800">{{ $usuario->email }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 font-medium">Rol</dt>
                    <dd class="text-gray-800">{{ $usuario->rol->nombre ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Cambio de contraseña --}}
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-lg font-bold text-gray-800 mb-1">Cambiar contraseña</h2>
            <p class="text-xs text-gray-500 mb-4">La nueva contraseña debe tener al menos 8 caracteres.</p>

            @if(session('success'))
                <div class="bg-emerald-100 text-emerald-700 p-3 rounded-lg text-sm mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm mb-4">
                    <ul class="list-disc pl-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('perfil.password') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Contraseña actual</label>
                    <input type="password" name="password_actual" required autocomplete="current-password"
                           class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Nueva contraseña</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full mt-1 p-2 text-sm border rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 text-sm">
                        Actualizar contraseña
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>

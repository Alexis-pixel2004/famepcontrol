<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PerfilController extends Controller
{
    // Formulario del perfil del usuario con sesión (Producción o Calidad).
    public function edit()
    {
        // Defensa extra: los clientes usan el portal por código, no este panel.
        if (auth()->user()->esCliente()) {
            return redirect()->route('consulta.acceso');
        }

        return view('perfil.edit', ['usuario' => auth()->user()]);
    }

    // Cambio de contraseña del propio usuario.
    public function updatePassword(Request $request)
    {
        if (auth()->user()->esCliente()) {
            return redirect()->route('consulta.acceso');
        }

        // El guard 'web' compara "password_actual" contra getAuthPassword(),
        // que en este proyecto devuelve password_hash.
        $validated = $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password'        => ['required', 'string', 'min:8', 'confirmed', 'different:password_actual'],
        ], [
            'password_actual.required'       => 'Debes ingresar tu contraseña actual.',
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.required'              => 'La nueva contraseña es obligatoria.',
            'password.min'                   => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'             => 'La confirmación de la contraseña no coincide.',
            'password.different'             => 'La nueva contraseña debe ser distinta a la actual.',
        ]);

        auth()->user()->update([
            'password_hash' => Hash::make($validated['password']),
        ]);

        // Nivel 2 (explícito): el cambio de contraseña no es un cambio de fila
        // sensible de auditar por sí solo. Se registra la ACCIÓN, nunca la clave.
        AuditoriaService::registrar(
            'editado',
            'usuarios',
            (int) auth()->id(),
            null,
            ['password_hash' => '(cambiada)']
        );

        // Rota el id de sesión tras un cambio de credenciales.
        $request->session()->regenerate();

        return redirect()->back()->with('success', 'Contraseña actualizada correctamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\EvidenciaFotografica;
use App\Models\FasePieza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenciaController extends Controller
{
    public function store(Request $request, $fasePiezaId)
    {
        if (! auth()->user()->esCalidad()) {
            return redirect()->back()
                ->with('error', 'Solo el Jefe de Calidad puede adjuntar evidencias.')
                ->withInput();
        }

        // D9: la fase debe existir y seguir admitiendo cambios. Sin esto, un
        // id inválido provocaba un 500 y una fase cerrada aceptaba fotos.
        $fase = FasePieza::with('estatusFase')->find($fasePiezaId);

        if ($fase === null) {
            return redirect()->back()
                ->with('error', 'La fase indicada no existe.')
                ->withInput();
        }

        if ($fase->estaCerrada()) {
            return redirect()->back()
                ->with('error', 'No se pueden adjuntar evidencias: la fase está en estado "'
                    . ($fase->estatusFase?->nombre ?? 'cerrada')
                    . '" y ya no admite cambios.')
                ->withInput();
        }

        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // Máx 5MB
        ], [
            'foto.required' => 'Debes seleccionar o tomar una fotografía.',
            'foto.image' => 'El archivo debe ser una imagen válida.',
            'foto.max' => 'La imagen no debe pesar más de 5 MB.',
        ]);

        if ($request->hasFile('foto')) {
            // Guardar imagen en la carpeta storage/app/public/evidencias
            $path = $request->file('foto')->store('evidencias', 'public');

            EvidenciaFotografica::create([
                'fase_pieza_id' => $fasePiezaId,
                'ruta_archivo' => $path,
                'fecha_subida' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Evidencia fotográfica guardada.');
    }
}
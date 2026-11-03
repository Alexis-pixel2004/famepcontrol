<?php

namespace App\Http\Controllers;

use App\Models\EvidenciaFotografica;
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
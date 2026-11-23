<?php

namespace App\Http\Controllers;

use App\Models\Pieza;
use App\Models\CatEstatusPieza;
use App\Models\CatTipoEstructura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PiezaController extends Controller
{
    public function store(Request $request, $obraId)
    {
        // La marca se guarda en mayúsculas; se normaliza antes de validar para
        // que la unicidad por obra no dependa de cómo la escriba el usuario.
        $request->merge([
            'clave_marca_pieza' => strtoupper(trim((string) $request->input('clave_marca_pieza'))),
            // RN-25: el alta fija la cantidad en 1 aunque venga otro valor.
            'cantidad'          => 1,
        ]);

        $validated = $request->validate([
            'clave_marca_pieza' => [
                'required', 'string', 'max:50',
                Rule::unique('piezas', 'clave_marca_pieza')
                    ->where(fn ($q) => $q->where('obra_id', $obraId)),
            ],
            'tipo_estructura_id' => 'required|exists:cat_tipos_estructura,id',
            'peso_kg' => 'required|numeric|min:0',
            'cantidad' => 'required|integer|in:1',
        ], [
            'clave_marca_pieza.unique' => 'Ya existe una pieza con esa marca en esta obra.',
        ]);

        $estatusPendiente = CatEstatusPieza::where('nombre', 'Pendiente')->first();

        $nuevaPieza = Pieza::create([
            'obra_id' => $obraId,
            'tipo_estructura_id' => $validated['tipo_estructura_id'],
            'estatus_id' => $estatusPendiente->id ?? 1,
            'clave_marca_pieza' => $validated['clave_marca_pieza'],
            'peso_kg' => $validated['peso_kg'],
            'cantidad' => $validated['cantidad'],
        ]);

        $nuevaPieza->obra->recalcularEstatus();

        return redirect()->route('obras.show', $obraId)->with('success', 'Pieza agregada correctamente.');
    }

    // Formulario de edición de una Pieza existente
    public function edit($id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('obras.index')
                ->with('error', 'Solo el Jefe de Producción puede editar piezas.');
        }

        $pieza = Pieza::with(['obra', 'tipoEstructura', 'estatus'])->findOrFail($id);
        $tiposEstructura = CatTipoEstructura::all();

        return view('piezas.edit', compact('pieza', 'tiposEstructura'));
    }

    // Actualizar una Pieza existente
    public function update(Request $request, $id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('obras.index')
                ->with('error', 'Solo el Jefe de Producción puede editar piezas.');
        }

        $pieza = Pieza::findOrFail($id);

        // Una pieza con avance (fases registradas) solo permite editar sus
        // datos descriptivos: la marca y el tipo de estructura quedan fijos
        // para no confundir el historial de producción.
        $conAvance = ! $pieza->sinFasesRegistradas();

        $reglas = [
            'peso_kg'  => 'required|numeric|min:0',
            'cantidad' => 'required|integer|min:1',
        ];

        if (! $conAvance) {
            $reglas['clave_marca_pieza'] = [
                'required', 'string', 'max:50',
                Rule::unique('piezas', 'clave_marca_pieza')
                    ->where(fn ($q) => $q->where('obra_id', $pieza->obra_id))
                    ->ignore($pieza->id),
            ];
            $reglas['tipo_estructura_id'] = 'required|exists:cat_tipos_estructura,id';
        }

        $validated = $request->validate($reglas, [
            'clave_marca_pieza.unique' => 'Ya existe una pieza con esa marca en esta obra.',
        ]);

        $datos = [
            'peso_kg'  => $validated['peso_kg'],
            'cantidad' => $validated['cantidad'],
        ];

        if (! $conAvance) {
            $datos['clave_marca_pieza'] = strtoupper($validated['clave_marca_pieza']);
            $datos['tipo_estructura_id'] = $validated['tipo_estructura_id'];
        }

        $pieza->update($datos);
        $pieza->recalcularEstatus();

        return redirect()->route('piezas.show', $pieza->id)->with('success', 'Pieza actualizada correctamente.');
    }

    // Eliminar una Pieza sin avance (sin fases registradas)
    public function destroy($id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('obras.index')
                ->with('error', 'Solo el Jefe de Producción puede eliminar piezas.');
        }

        $pieza = Pieza::with('obra')->findOrFail($id);

        // Solo se eliminan piezas sin fases: una vez iniciada la producción
        // se conserva el historial (fases, dictámenes, evidencias).
        if (! $pieza->sinFasesRegistradas()) {
            return redirect()->route('obras.show', $pieza->obra_id)
                ->with('error', 'Solo se pueden eliminar piezas sin fases registradas (sin avance de producción).');
        }

        $obra = $pieza->obra;

        DB::transaction(function () use ($pieza) {
            // El FK fases_pieza.pieza_id tiene ON DELETE CASCADE; al no haber
            // fases registradas, el borrado queda limpio.
            $pieza->delete();
        });

        // El estatus de la obra se deriva de sus piezas: sin esta pieza,
        // puede pasar de Pendiente a Finalizada (o viceversa) si aplica.
        $obra?->recalcularEstatus();

        return redirect()->route('obras.show', $obra?->id)->with('success', 'Pieza eliminada correctamente.');
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\CatFase;
use App\Models\Operario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperarioController extends Controller
{
    // Listado de operarios (solo Jefe de Producción).
    public function index()
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('dashboard')
                ->with('error', 'Solo el Jefe de Producción puede gestionar operarios.');
        }

        $operarios = Operario::with('fasesHabilitadas')
            ->withCount('fases')
            ->orderBy('nombre')
            ->get();

        return view('operarios.index', compact('operarios'));
    }

    // Formulario de alta.
    public function create()
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('operarios.index')
                ->with('error', 'Solo el Jefe de Producción puede gestionar operarios.');
        }

        $fases = CatFase::orderBy('orden_secuencia')->get();

        return view('operarios.create', compact('fases'));
    }

    // Guardar operario nuevo + sus fases habilitadas.
    public function store(Request $request)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('operarios.index')
                ->with('error', 'Solo el Jefe de Producción puede gestionar operarios.');
        }

        $validated = $request->validate([
            'clave_empleado' => 'required|string|max:20|unique:operarios,clave_empleado',
            'nombre'         => 'required|string|max:100',
            'apellido'       => 'required|string|max:100',
            'activo'         => 'nullable|boolean',
            'fases'          => 'required|array|min:1',
            'fases.*'        => 'integer|exists:cat_fases,id',
        ], [
            'clave_empleado.unique' => 'La clave de empleado ya existe.',
            'fases.required'        => 'Debe habilitar al menos una fase para el operario.',
            'fases.min'             => 'Debe habilitar al menos una fase para el operario.',
        ]);

        DB::transaction(function () use ($validated) {
            $operario = Operario::create([
                'clave_empleado' => $validated['clave_empleado'],
                'nombre'         => $validated['nombre'],
                'apellido'       => $validated['apellido'],
                'activo'         => (bool) ($validated['activo'] ?? true),
            ]);

            $operario->fasesHabilitadas()->sync($validated['fases']);
        });

        return redirect()->route('operarios.index')->with('success', 'Operario registrado correctamente.');
    }

    // Formulario de edición.
    public function edit($id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('operarios.index')
                ->with('error', 'Solo el Jefe de Producción puede gestionar operarios.');
        }

        $operario = Operario::with('fasesHabilitadas')->findOrFail($id);
        $fases    = CatFase::orderBy('orden_secuencia')->get();

        return view('operarios.edit', compact('operario', 'fases'));
    }

    // Actualizar operario y sus fases habilitadas.
    public function update(Request $request, $id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('operarios.index')
                ->with('error', 'Solo el Jefe de Producción puede gestionar operarios.');
        }

        $operario = Operario::findOrFail($id);

        $validated = $request->validate([
            'clave_empleado' => ['required', 'string', 'max:20', Rule::unique('operarios', 'clave_empleado')->ignore($operario->id)],
            'nombre'         => 'required|string|max:100',
            'apellido'       => 'required|string|max:100',
            'activo'         => 'nullable|boolean',
            'fases'          => 'required|array|min:1',
            'fases.*'        => 'integer|exists:cat_fases,id',
        ], [
            'clave_empleado.unique' => 'La clave de empleado ya existe.',
            'fases.required'        => 'Debe habilitar al menos una fase para el operario.',
            'fases.min'             => 'Debe habilitar al menos una fase para el operario.',
        ]);

        $error = DB::transaction(function () use ($operario, $validated) {
            // Serializa con las asignaciones de fase que bloquean al operario.
            $operario = Operario::whereKey($operario->id)->lockForUpdate()->firstOrFail();

            $activas = $operario->fases()
                ->whereHas('estatusFase', fn ($q) => $q->whereIn('nombre', Operario::ESTADOS_QUE_OCUPAN))
                ->with(['fase', 'pieza.obra', 'estatusFase'])
                ->get();

            $activo = (bool) ($validated['activo'] ?? true);

            // A9: no desactivar a un operario que todavía tiene una tarea abierta.
            if (! $activo && $activas->isNotEmpty()) {
                return 'No se puede desactivar a ' . trim($operario->nombre . ' ' . $operario->apellido)
                    . ': tiene ' . $this->descripcionFaseActiva($activas->first())
                    . '. Finalice esa tarea o reasígnela antes de desactivarlo.';
            }

            // A8: no quitar una habilitación que el operario está usando ahora.
            $fases = array_map('intval', $validated['fases']);
            $removidas = $activas->filter(
                fn ($fase) => ! in_array((int) $fase->fase_id, $fases, true)
            );

            if ($removidas->isNotEmpty()) {
                return 'No se puede quitar la habilitación de fase: el operario tiene '
                    . $this->descripcionFaseActiva($removidas->first())
                    . '. Finalice esa tarea o reasígnela antes de cambiar sus fases.';
            }

            $operario->update([
                'clave_empleado' => $validated['clave_empleado'],
                'nombre'         => $validated['nombre'],
                'apellido'       => $validated['apellido'],
                'activo'         => $activo,
            ]);

            $operario->fasesHabilitadas()->sync($validated['fases']);

            return null;
        });

        if ($error !== null) {
            return redirect()->route('operarios.index')->with('error', $error)->withInput();
        }

        return redirect()->route('operarios.index')->with('success', 'Operario actualizado correctamente.');
    }

    // Describe la tarea abierta que impide desactivar o deshabilitar al operario.
    private function descripcionFaseActiva(\App\Models\FasePieza $fase): string
    {
        return 'la fase "' . ($fase->fase?->nombre ?? 'otra fase')
            . '" de la marca ' . ($fase->pieza?->clave_marca_pieza ?? 'sin marca')
            . ' (' . ($fase->pieza?->obra?->codigo_obra ?? 'sin obra') . '), que sigue en "'
            . ($fase->estatusFase?->nombre ?? 'proceso') . '"';
    }

    // Eliminar operario: solo si no tiene fases registradas (historial).
    public function destroy($id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('operarios.index')
                ->with('error', 'Solo el Jefe de Producción puede gestionar operarios.');
        }

        $operario = Operario::findOrFail($id);

        // Se conserva el historial: un operario con fases registradas no se borra.
        if ($operario->fases()->exists()) {
            return redirect()->route('operarios.index')
                ->with('error', 'No se puede eliminar: el operario ya tiene fases registradas. Puede desactivarlo en su lugar.');
        }

        DB::transaction(function () use ($operario) {
            // La tabla pivote tiene ON DELETE CASCADE: se limpian sus fases habilitadas.
            $operario->fasesHabilitadas()->detach();
            $operario->delete();
        });

        return redirect()->route('operarios.index')->with('success', 'Operario eliminado correctamente.');
    }
}

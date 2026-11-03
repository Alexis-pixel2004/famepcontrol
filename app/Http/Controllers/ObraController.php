<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\Pieza;
use App\Models\CatTipoEstructura;
use App\Models\CatEstatusPieza;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ObraController extends Controller
{
    // Listado principal de Obras
    public function index()
    {
        $obras = Obra::withCount('piezas')->orderBy('id', 'desc')->get();
        return view('obras.index', compact('obras'));
    }

    // Formulario para nueva Obra
    public function create()
    {
        $clientes = User::where('rol_id', 3)->orderBy('nombre')->get(['id', 'nombre']);
        return view('obras.create', compact('clientes'));
    }

    // Guardar nueva Obra
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_obra' => 'required|string|unique:obras,codigo_obra',
            'nombre_obra' => 'required|string|max:255',
            'cliente' => 'required|string|max:255',
            'cliente_usuario_id' => 'nullable|exists:usuarios,id',
            'fecha_inicio' => 'required|date',
            'fecha_compromiso' => 'required|date|after_or_equal:fecha_inicio',
        ], [
            'codigo_obra.unique' => 'El código de obra ya existe.',
            'fecha_compromiso.after_or_equal' => 'La fecha de compromiso no puede ser anterior al inicio.',
        ]);

        // Estatus inicial derivado: la obra nace sin avance.
        $validated['estatus'] = 'Pendiente';

        Obra::create($validated);

        return redirect()->route('obras.index')->with('success', 'Obra registrada correctamente.');
    }

    // Detalle de Obra y sus Piezas
public function show($id)
{
    // 1. Carga de la obra con sus relaciones (Mantiene tu lógica previa)
    $obra = Obra::with([
        'piezas.tipoEstructura',
        'piezas.estatus',
        'piezas.fases.estatusFase',
        'piezas.fases.fase',
    ])->findOrFail($id);
    
    // 2. Variables para el formulario de agregar piezas (Mantiene tu lógica previa)
    $tiposEstructura = CatTipoEstructura::all();
    $estatusInicial  = CatEstatusPieza::where('nombre', 'Pendiente')->first();

    // 3. Cálculo de KPIs sobre las piezas de ESTA obra
    $totalPiezasObra = $obra->piezas->sum('cantidad');

    $piezasLiberadas = $obra->piezas
        ->filter(fn($p) => $p->estatus?->nombre === 'Aprobada')
        ->sum('cantidad');

    $piezasReproceso = $obra->piezas
        ->filter(fn($p) => in_array($p->estatus?->nombre, ['En Revisión', 'Detenida']))
        ->sum('cantidad');

    $piezasPendientes = $obra->piezas
        ->filter(fn($p) => in_array($p->estatus?->nombre, ['Pendiente', 'En Proceso']))
        ->sum('cantidad');

    // 4. RF-03: avance acumulado de la obra (fases completadas / catálogo x piezas)
    $totalFases      = Pieza::totalFasesEsperadas();
    $completadasObra = $obra->piezas->sum(
        fn($p) => $p->fases->filter(fn($f) => (bool) $f->estatusFase?->es_completada)->count()
    );
    $esperadasObra = $obra->piezas->count() * $totalFases;

    $avanceObra = [
        'completadas' => $completadasObra,
        'esperadas'   => $esperadasObra,
        'porcentaje'  => $esperadasObra > 0 ? (int) round($completadasObra / $esperadasObra * 100) : 0,
    ];

    // 5. Retornamos TODAS las variables
    return view('obras.show', compact(
        'obra',
        'tiposEstructura',
        'estatusInicial',
        'totalPiezasObra',
        'piezasLiberadas',
        'piezasReproceso',
        'piezasPendientes',
        'avanceObra',
        'totalFases'
    ));
}

    // Formulario de edición de una Obra existente
    public function edit($id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('obras.index')
                ->with('error', 'Solo el Jefe de Producción puede editar obras.');
        }

        $obra = Obra::findOrFail($id);
        $clientes = User::where('rol_id', 3)->orderBy('nombre')->get(['id', 'nombre']);

        return view('obras.edit', compact('obra', 'clientes'));
    }

    // Actualizar una Obra existente
    public function update(Request $request, $id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('obras.index')
                ->with('error', 'Solo el Jefe de Producción puede editar obras.');
        }

        $obra = Obra::findOrFail($id);

        $validated = $request->validate([
            'codigo_obra' => ['required', 'string', Rule::unique('obras', 'codigo_obra')->ignore($obra->id)],
            'nombre_obra' => 'required|string|max:255',
            'cliente' => 'required|string|max:255',
            'cliente_usuario_id' => 'nullable|exists:usuarios,id',
            'fecha_inicio' => 'required|date',
            'fecha_compromiso' => 'required|date|after_or_equal:fecha_inicio',
        ], [
            'codigo_obra.unique' => 'El código de obra ya existe.',
            'fecha_compromiso.after_or_equal' => 'La fecha de compromiso no puede ser anterior al inicio.',
        ]);

        // El estatus es derivado de las piezas (recalcularEstatus): no se edita aquí.
        $obra->update($validated);

        return redirect()->route('obras.index')->with('success', 'Obra actualizada correctamente.');
    }

    // Eliminar una Obra sin avance (estatus Pendiente)
    public function destroy($id)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->route('obras.index')
                ->with('error', 'Solo el Jefe de Producción puede eliminar obras.');
        }

        $obra = Obra::findOrFail($id);

        // Solo se eliminan obras sin avance: una vez iniciada la producción
        // se conserva el historial (piezas, fases, dictámenes, evidencias).
        if ($obra->estatus !== 'Pendiente') {
            return redirect()->route('obras.index')
                ->with('error', 'Solo se pueden eliminar obras Pendientes (sin avance de producción).');
        }

        DB::transaction(function () use ($obra) {
            // El FK piezas.obra_id tiene ON DELETE CASCADE: al borrar la obra
            // se eliminan sus piezas (y su descendencia) en la misma transacción.
            $obra->delete();
        });

        return redirect()->route('obras.index')->with('success', 'Obra eliminada correctamente.');
    }
}
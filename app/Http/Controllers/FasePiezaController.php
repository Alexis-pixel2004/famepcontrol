<?php

namespace App\Http\Controllers;

use App\Models\Pieza;
use App\Models\FasePieza;
use App\Models\CatFase;
use App\Models\CatEstatusFase;
use App\Models\Operario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FasePiezaController extends Controller
{
    // Mostrar pantalla de avance de la pieza
    public function show($piezaId)
    {
        $pieza = Pieza::with([
            'obra',
            'tipoEstructura',
            'estatus',
            'fases.fase',
            'fases.estatusFase',
            'fases.operario',
            'fases.observaciones.inspector',
            'fases.observaciones.editadoPor',
            'fases.observaciones.evidencias',
            'fases.evidencias',
        ])->findOrFail($piezaId);

        $fasesCat  = CatFase::orderBy('orden_secuencia')->get();
        $operarios = Operario::where('activo', true)->with('fasesHabilitadas')->get();

        $enProcesoId  = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
        $completadaId = CatEstatusFase::where('nombre', 'Completada')->value('id');

        return view('piezas.show', compact(
            'pieza',
            'fasesCat',
            'operarios',
            'enProcesoId',
            'completadaId'
        ));
    }

    // Registrar o actualizar avance de fase
    public function store(Request $request, $piezaId)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->back()
                ->with('error', 'Solo el Jefe de Producción puede registrar avances.')
                ->withInput();
        }

        $enProceso  = CatEstatusFase::where('nombre', 'En Proceso')->value('id');
        $completada = CatEstatusFase::where('nombre', 'Completada')->value('id');

        $validated = $request->validate([
            'fase_id'         => 'required|exists:cat_fases,id',
            'operario_id'     => 'required|exists:operarios,id',
            'estatus_fase_id' => 'required|in:' . $enProceso . ',' . $completada,
        ], [
            'estatus_fase_id.in' => 'El estado de la fase no es válido.',
        ]);

        $fasePieza = FasePieza::where('pieza_id', $piezaId)
            ->where('fase_id', $validated['fase_id'])
            ->first();

        $cerrando = (int) $validated['estatus_fase_id'] === (int) $completada;

        // REGLA 1: una fase ya terminada (aprobada por Calidad y cerrada
        // por Producción) no se puede volver a registrar ni modificar.
        if ($fasePieza?->terminada()) {
            return redirect()->back()
                ->with('error', 'Esta fase ya fue aprobada por Calidad y terminada por Producción. No se puede volver a registrar.')
                ->withInput();
        }

        // REGLA 1b: una marca rechazada queda detenida, tampoco se toca
        if ($fasePieza?->estaCerrada()) {
            return redirect()->back()
                ->with('error', 'Esta fase está en estado "' . $fasePieza->estatusFase?->nombre . '" y no se puede modificar.')
                ->withInput();
        }

        // REGLA 2: no repetir operario mientras la fase siga en "En Proceso".
        // Si la fase volvio de Calidad (reproceso) el mismo operario puede
        // retomar el trabajo, asi que ahi no se bloquea.
        $faseEnCurso = $fasePieza?->estatusFase?->nombre === 'En Proceso';

        if (! $cerrando && $faseEnCurso && (int) $fasePieza->operario_id === (int) $validated['operario_id']) {
            return redirect()->back()
                ->with('error', 'El operario seleccionado ya está asignado a esta fase en proceso. Seleccione uno diferente.')
                ->withInput();
        }

        // REGLA 2c: el operario debe estar habilitado para esa fase.
        // Un operario multifase (soldador/pintor) tendrá varias habilitadas;
        // uno de una sola etapa solo la suya. Sin fases configuradas = sin
        // restricción (operarios históricos que aún no se les asignan fases).
        $operario = Operario::find($validated['operario_id']);

        if ($operario !== null && ! $operario->puedeHacerFase($validated['fase_id'])) {
            $faseNombre = CatFase::whereKey($validated['fase_id'])->value('nombre') ?? 'esa fase';

            return redirect()->back()
                ->with('error', 'El operario ' . trim($operario->nombre . ' ' . $operario->apellido)
                    . ' no está habilitado para la fase "' . $faseNombre . '". Habilítelo en Operarios o elija otro.')
                ->withInput();
        }

        // REGLA 2b: una vez que Calidad aprobo, la unica transicion que
        // admite Produccion es el cierre. Devolverla a "En Proceso"
        // reabria la fase y permitiria un nuevo dictamen.
        if (! $cerrando && $fasePieza?->aprobadaPorCalidad()) {
            return redirect()->back()
                ->with('error', 'Esta fase ya fue aprobada por Calidad. Lo único que puede hacer Producción es marcarla como terminada.')
                ->withInput();
        }

        // REGLA 3: solo Producción cierra, y únicamente si Calidad ya aprobó
        if ($cerrando && ! $fasePieza?->aprobadaPorCalidad()) {
            return redirect()->back()
                ->with('error', 'No se puede cerrar la fase: Calidad aún no ha emitido el dictamen "Aprobado".')
                ->withInput();
        }

        FasePieza::updateOrCreate(
            [
                'pieza_id' => $piezaId,
                'fase_id'  => $validated['fase_id'],
            ],
            [
                'operario_id'        => $validated['operario_id'],
                'usuario_captura_id' => Auth::id(),
                'estatus_fase_id'    => $cerrando ? $completada : $enProceso,
                'fecha_inicio'       => $fasePieza?->fecha_inicio ?? now(),
                'fecha_termino'      => $cerrando ? now() : null,
            ]
        );

        $pieza = Pieza::findOrFail($piezaId);
        $pieza->recalcularEstatus();
        $pieza->obra->recalcularEstatus();

        return redirect()->route('piezas.show', $piezaId)->with(
            'success',
            $cerrando
                ? 'Fase cerrada correctamente.'
                : 'Avance de fase registrado correctamente.'
        );
    }
}

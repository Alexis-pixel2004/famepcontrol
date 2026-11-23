<?php

namespace App\Http\Controllers;

use App\Models\Pieza;
use App\Models\FasePieza;
use App\Models\CatFase;
use App\Models\CatEstatusFase;
use App\Models\Operario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        // Se localiza la fase antes de validar para poder reutilizar su
        // operario cuando se esté cerrando y el formulario no lo envíe.
        $faseIdSolicitada = $request->input('fase_id');
        $fasePieza = is_scalar($faseIdSolicitada)
            ? FasePieza::where('pieza_id', $piezaId)
                ->where('fase_id', $faseIdSolicitada)
                ->first()
            : null;

        $estatusSolicitado = $request->input('estatus_fase_id');
        $cerrando = is_scalar($estatusSolicitado)
            && (int) $estatusSolicitado === (int) $completada;

        // Al cerrar una fase ya registrada se conserva el operario asignado si
        // no se envió otro. El valor inyectado pasa por las mismas validaciones
        // (activo, habilitado, etc.), así que no se relaja ninguna regla.
        if ($cerrando && $fasePieza !== null && blank($request->input('operario_id'))) {
            $request->merge(['operario_id' => $fasePieza->operario_id]);
        }

        $validated = $request->validate([
            'fase_id'         => 'required|exists:cat_fases,id',
            // Un operario desactivado no puede recibir trabajo nuevo.
            'operario_id'     => [
                'required',
                Rule::exists('operarios', 'id')->where('activo', true),
            ],
            'estatus_fase_id' => 'required|in:' . $enProceso . ',' . $completada,
        ], [
            'estatus_fase_id.in' => 'El estado de la fase no es válido.',
        ]);

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

        $error = DB::transaction(function () use ($validated, $cerrando, $piezaId, $fasePieza, $enProceso, $completada) {
            // REGLA 4: las fases se ejecutan en orden según cat_fases.orden_secuencia.
            // Se revisa aquí, con la pieza bloqueada, para serializar dos altas
            // simultáneas que intenten adelantarse a la fase anterior.
            $faseDestino = CatFase::findOrFail($validated['fase_id']);
            $piezaBloqueada = Pieza::whereKey($piezaId)->lockForUpdate()->firstOrFail();

            $anteriores = CatFase::where('orden_secuencia', '<', $faseDestino->orden_secuencia)
                ->orderBy('orden_secuencia')
                ->get();

            foreach ($anteriores as $anterior) {
                $previa = $piezaBloqueada->fases()->where('fase_id', $anterior->id)->first();

                if ($previa === null || ! $previa->terminada()) {
                    return 'No se puede registrar la fase "' . $faseDestino->nombre
                        . '" hasta que "' . $anterior->nombre . '" esté completada.';
                }
            }

            // Bloquea al operario para serializar dos asignaciones simultáneas.
            $operario = Operario::whereKey($validated['operario_id'])->lockForUpdate()->first();

            // REGLA 2d: un operario solo puede tener una fase activa a la vez.
            // Se excluye la fase que se está guardando para permitir retomar un
            // reproceso con el mismo operario.
            if (! $cerrando) {
                $conflicto = $operario?->faseActiva($fasePieza?->id);

                if ($conflicto !== null) {
                    return $this->mensajeOperarioOcupado($operario, $conflicto);
                }
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

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error)->withInput();
        }

        return redirect()->route('piezas.show', $piezaId)->with(
            'success',
            $cerrando
                ? 'Fase cerrada correctamente.'
                : 'Avance de fase registrado correctamente.'
        );
    }

    /**
     * Formulario para cambiar el operario asignado a una fase.
     */
    public function edit($fasePiezaId)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->back()->with('error', 'Solo el Jefe de Producción puede editar asignaciones.');
        }

        $fasePieza = FasePieza::with(['pieza.obra', 'fase', 'estatusFase', 'operario'])
            ->findOrFail($fasePiezaId);

        if ($fasePieza->terminada() || $fasePieza->estaCerrada()) {
            return redirect()->back()->with('error', 'Esta fase ya está cerrada y no admite cambios.');
        }

        $operarios = Operario::where('activo', true)->with('fasesHabilitadas')->get();

        return view('piezas.fases.edit', compact('fasePieza', 'operarios'));
    }

    /**
     * Cambia el operario asignado. No permite cambiar la fase ni el estatus.
     */
    public function update(Request $request, $fasePiezaId)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->back()
                ->with('error', 'Solo el Jefe de Producción puede editar asignaciones.')
                ->withInput();
        }

        $fasePieza = FasePieza::with(['pieza', 'fase', 'estatusFase'])->findOrFail($fasePiezaId);

        if ($fasePieza->terminada() || $fasePieza->estaCerrada()) {
            return redirect()->back()
                ->with('error', 'Esta fase ya está cerrada y no admite cambios.')
                ->withInput();
        }

        $validated = $request->validate([
            'operario_id' => [
                'required',
                Rule::exists('operarios', 'id')->where('activo', true),
            ],
        ]);

        $operario = Operario::findOrFail($validated['operario_id']);

        if (! $operario->puedeHacerFase($fasePieza->fase_id)) {
            return redirect()->back()
                ->with('error', 'El operario no está habilitado para esta fase.')
                ->withInput();
        }

        $error = DB::transaction(function () use ($fasePieza, $operario) {
            $bloqueado = Operario::whereKey($operario->id)->lockForUpdate()->first();
            $conflicto = $bloqueado?->faseActiva($fasePieza->id);

            if ($conflicto !== null) {
                return $this->mensajeOperarioOcupado($bloqueado, $conflicto);
            }

            $fasePieza->update([
                'operario_id'        => $operario->id,
                'usuario_captura_id' => Auth::id(),
            ]);

            $pieza = Pieza::findOrFail($fasePieza->pieza_id);
            $pieza->recalcularEstatus();
            $pieza->obra->recalcularEstatus();

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error)->withInput();
        }

        return redirect()->route('piezas.show', $fasePieza->pieza_id)
            ->with('success', 'Asignación de operario actualizada correctamente.');
    }

    /**
     * Elimina la asignación de una fase sin historial de inspección.
     */
    public function destroy($fasePiezaId)
    {
        if (! auth()->user()->esProduccion()) {
            return redirect()->back()->with('error', 'Solo el Jefe de Producción puede eliminar asignaciones.');
        }

        $fasePieza = FasePieza::with(['pieza', 'estatusFase'])->findOrFail($fasePiezaId);

        if ($fasePieza->terminada() || $fasePieza->estaCerrada()) {
            return redirect()->back()->with('error', 'Esta fase ya está cerrada y no se puede eliminar.');
        }

        if ($fasePieza->observaciones()->exists() || $fasePieza->evidencias()->exists()) {
            return redirect()->back()
                ->with('error', 'La fase ya tiene dictámenes o evidencias. No se puede eliminar la asignación.');
        }

        $piezaId = $fasePieza->pieza_id;

        DB::transaction(function () use ($fasePieza, $piezaId) {
            $fasePieza->delete();

            $pieza = Pieza::findOrFail($piezaId);
            $pieza->recalcularEstatus();
            $pieza->obra->recalcularEstatus();
        });

        return redirect()->route('piezas.show', $piezaId)
            ->with('success', 'Asignación de fase eliminada correctamente.');
    }

    /**
     * Mensaje único para la regla de "un operario, una tarea activa".
     */
    private function mensajeOperarioOcupado(Operario $operario, FasePieza $conflicto): string
    {
        return 'El operario ' . trim($operario->nombre . ' ' . $operario->apellido)
            . ' ya está asignado a la fase "' . ($conflicto->fase?->nombre ?? 'otra fase')
            . '" de la marca ' . ($conflicto->pieza?->clave_marca_pieza ?? 'sin marca')
            . ' (' . ($conflicto->pieza?->obra?->codigo_obra ?? 'sin obra') . '), que sigue en "'
            . ($conflicto->estatusFase?->nombre ?? 'proceso')
            . '". Finalice esa fase o elija otro operario.';
    }
}

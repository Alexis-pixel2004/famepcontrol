<?php

namespace App\Http\Controllers;

use App\Models\ObservacionCalidad;
use App\Models\EvidenciaFotografica;
use App\Models\FasePieza;
use App\Models\CatEstatusFase;
use App\Jobs\SendWhatsAppAlertJob;
use App\Support\FechaLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ObservacionCalidadController extends Controller
{
    public function store(Request $request, $fasePiezaId)
    {
        if (! auth()->user()->esCalidad()) {
            return redirect()->back()
                ->with('error', 'Solo el Jefe de Calidad puede dictaminar.')
                ->withInput();
        }

        $validated = $request->validate([
            'descripcion' => 'required|string|max:1000',
            'resultado'   => 'required|in:Aprobado,Rechazado,Enviado a Reproceso',
            'foto'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        // Cada dictamen mueve la fase a un estado del catálogo.
        // "Aprobado" NO cierra la fase: deja la pieza esperando el
        // cierre de Producción (completar la fase).
        $estatusDestino = match ($validated['resultado']) {
            'Aprobado'            => 'Aprobada por Calidad',
            'Enviado a Reproceso' => 'En Reproceso',
            'Rechazado'           => 'Rechazada',
        };

        $estatusId = CatEstatusFase::where('nombre', $estatusDestino)->value('id');

        $error = DB::transaction(function () use ($request, $fasePiezaId, $validated, $estatusId) {
            // El bloqueo de fila serializa dos envíos simultáneos sobre la
            // misma fase: el segundo espera y luego ve el estado que dejó
            // el primero, en lugar de leer el anterior y duplicar el dictamen.
            $fasePieza = FasePieza::with('pieza')->lockForUpdate()->findOrFail($fasePiezaId);

            // Una vez que Calidad aprobó, la marca queda a espera del cierre
            // de Producción: no se admiten nuevos dictamenes ni comentarios.
            if ($fasePieza->terminada()) {
                return 'Esta fase ya fue aprobada por Calidad y cerrada por Producción. No se pueden registrar nuevos dictamenes.';
            }

            if ($fasePieza->aprobadaPorCalidad()) {
                return 'Esta fase ya fue aprobada por Calidad. Producción solo puede marcarla como terminada; después de eso no se admiten más dictamenes.';
            }

            if (! $fasePieza->admiteDictamen()) {
                return 'No se puede dictaminar una fase en estado "' . $fasePieza->estatusFase?->nombre . '".';
            }

            // 1. Dictamen de calidad
            $dictamen = ObservacionCalidad::create([
                'fase_pieza_id' => $fasePieza->id,
                'usuario_id'    => Auth::id(),
                'descripcion'   => $validated['descripcion'],
                'resultado'     => $validated['resultado'],
            ]);

            // 2. Evidencia fotográfica, ligada al dictamen (observacion_calidad_id)
            //    para que editar/eliminar el dictamen pueda gestionar sus fotos.
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('evidencias', 'public');

                EvidenciaFotografica::create([
                    'fase_pieza_id'          => $fasePieza->id,
                    'observacion_calidad_id' => $dictamen->id,
                    'ruta_archivo'           => $path,
                ]);
            }

            // 3. Estado de la fase + fecha del dictamen
            $fasePieza->update([
                'estatus_fase_id' => $estatusId,
                'fecha_dictamen'  => now(),
            ]);

            // 4. Derivar el estatus de la pieza a partir de sus fases
            $fasePieza->pieza->recalcularEstatus();

            // 5. Propagar el cambio al estatus de la obra
            $fasePieza->pieza->obra->recalcularEstatus();

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        // RF-33: alerta de WhatsApp (solo calidad) cuando el dictamen es
        // un rechazo o una observación. Se relee la fase ya sin el
        // lockForUpdate para no sostener la fila durante el HTTP.
        $this->notificarWhatsApp($fasePiezaId, $validated['resultado'], $validated['descripcion']);

        return redirect()->back()->with('success', 'Dictamen registrado: ' . $validated['resultado']);
    }

    // Formulario de edición de un dictamen existente
    public function edit($id)
    {
        if (! auth()->user()->esCalidad()) {
            return redirect()->route('dashboard')
                ->with('error', 'Solo el Jefe de Calidad puede editar dictámenes.');
        }

        $dictamen = ObservacionCalidad::with(['evidencias'])->findOrFail($id);
        $fasePieza = FasePieza::with(['pieza.obra', 'fase'])->findOrFail($dictamen->fase_pieza_id);

        // Los dictámenes de una fase ya finalizada son de solo lectura.
        if ($fasePieza->dictamenesInmutables()) {
            return redirect()->route('piezas.show', $fasePieza->pieza_id)
                ->with('error', 'La fase ya fue cerrada por Producción: sus dictámenes son de solo lectura.');
        }

        return view('piezas.dictamen_edit', compact('dictamen', 'fasePieza'));
    }

    // Actualizar un dictamen existente
    public function update(Request $request, $id)
    {
        if (! auth()->user()->esCalidad()) {
            return redirect()->route('dashboard')
                ->with('error', 'Solo el Jefe de Calidad puede editar dictámenes.');
        }

        $dictamen = ObservacionCalidad::findOrFail($id);

        $validated = $request->validate([
            'descripcion'   => 'required|string|max:1000',
            'resultado'     => 'required|in:Aprobado,Rechazado,Enviado a Reproceso',
            'foto'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'quitar_foto'   => 'nullable|array',
            'quitar_foto.*' => 'integer|exists:evidencias_fotograficas,id',
        ]);

        // Se guarda el resultado previo para no re-notificar cuando la edición
        // solo cambia la descripción o las fotos.
        $resultadoAnterior = $dictamen->resultado;

        $error = DB::transaction(function () use ($request, $dictamen, $validated) {
            // El dictamen puede haber cambiado el estado de la fase; se
            // recalcula desde el historial de dictámenes (manda el último).
            $fasePieza = FasePieza::lockForUpdate()->findOrFail($dictamen->fase_pieza_id);

            // Revalidación dentro de la transacción: Producción pudo haber
            // cerrado la fase entre que Calidad abrió el formulario y lo envió.
            if ($fasePieza->dictamenesInmutables()) {
                return 'La fase ya fue cerrada por Producción: sus dictámenes son de solo lectura.';
            }

            // Se marca "editado" solo si el contenido realmente cambió
            // (descripcion, resultado o gestión de fotos), para que la
            // auditoría refleje modificaciones reales y no un envío vacío.
            $huboCambios = $dictamen->descripcion !== $validated['descripcion']
                || $dictamen->resultado !== $validated['resultado']
                || ! empty($validated['quitar_foto'])
                || $request->hasFile('foto');

            $atributos = [
                'descripcion' => $validated['descripcion'],
                'resultado'   => $validated['resultado'],
            ];

            if ($huboCambios) {
                $atributos['editado']     = true;
                $atributos['editado_at']  = now();
                $atributos['editado_por'] = Auth::id();
            }

            $dictamen->update($atributos);

            // 1. Quitar evidencias marcadas (registro + archivo físico)
            if (! empty($validated['quitar_foto'])) {
                $evidencias = EvidenciaFotografica::where('observacion_calidad_id', $dictamen->id)
                    ->whereIn('id', $validated['quitar_foto'])
                    ->get();

                foreach ($evidencias as $evidencia) {
                    Storage::disk('public')->delete($evidencia->ruta_archivo);
                    $evidencia->delete();
                }
            }

            // 2. Nuevas evidencias
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('evidencias', 'public');

                EvidenciaFotografica::create([
                    'fase_pieza_id'          => $fasePieza->id,
                    'observacion_calidad_id' => $dictamen->id,
                    'ruta_archivo'           => $path,
                ]);
            }

            // 3. Recalcular estado de la fase desde el historial de dictámenes
            $fasePieza->recalcularEstadoDesdeDictamenes();
            $fasePieza->update(['fecha_dictamen' => now()]);

            // 4. Propagar a pieza y obra
            $fasePieza->pieza->recalcularEstatus();
            $fasePieza->pieza->obra->recalcularEstatus();

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        // RF-33: re-notificar solo si el resultado realmente cambió y el nuevo
        // es rechazo/reproceso. Editar la descripción de un dictamen que ya
        // estaba en reproceso no debe generar otro mensaje.
        if ($resultadoAnterior !== $validated['resultado']) {
            $this->notificarWhatsApp($dictamen->fase_pieza_id, $validated['resultado'], $validated['descripcion']);
        }

        return redirect()->route('piezas.show', $dictamen->fasePieza?->pieza_id)
            ->with('success', 'Dictamen actualizado.');
    }

    // Eliminar un dictamen existente
    public function destroy($id)
    {
        if (! auth()->user()->esCalidad()) {
            return redirect()->route('dashboard')
                ->with('error', 'Solo el Jefe de Calidad puede eliminar dictámenes.');
        }

        $dictamen = ObservacionCalidad::with(['evidencias'])->findOrFail($id);
        $fasePiezaId = $dictamen->fase_pieza_id;
        $piezaId     = $dictamen->fasePieza?->pieza_id;

        $error = DB::transaction(function () use ($dictamen, $fasePiezaId) {
            $fasePieza = FasePieza::lockForUpdate()->findOrFail($fasePiezaId);

            // Los dictámenes de una fase ya finalizada son de solo lectura.
            if ($fasePieza->dictamenesInmutables()) {
                return 'La fase ya fue cerrada por Producción: sus dictámenes son de solo lectura.';
            }

            // Borra los archivos físicos de las evidencias ligadas al dictamen
            // (el cascade de la BD solo borra los registros, no los archivos).
            foreach ($dictamen->evidencias as $evidencia) {
                Storage::disk('public')->delete($evidencia->ruta_archivo);
            }

            // El FK evidencias_fotograficas.observacion_calidad_id es ON DELETE
            // CASCADE: elimina los registros de evidencia ligados.
            $dictamen->delete();

            // El estado de la fase se deriva del último dictamen vigente.
            $fasePieza->recalcularEstadoDesdeDictamenes();

            $fasePieza->pieza->recalcularEstatus();
            $fasePieza->pieza->obra->recalcularEstatus();

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        // RF-33: eliminar un dictamen es una corrección administrativa, no un
        // hallazgo nuevo. Antes se reenviaba el dictamen que quedaba como
        // último vigente, lo que producía una alerta "fantasma": un rechazo
        // histórico con la fecha de hoy y el inspector que borra. El rastro
        // de la eliminación queda en la auditoría.
        return redirect()->route('piezas.show', $piezaId)
            ->with('success', 'Dictamen eliminado.');
    }

    /**
     * Envía la alerta de WhatsApp (RF-33) para un dictamen. Solo dispara
     * cuando el resultado es un rechazo o un envío a reproceso.
     */
    private function notificarWhatsApp(int $fasePiezaId, string $resultado, string $descripcion): void
    {
        if (! in_array($resultado, ['Rechazado', 'Enviado a Reproceso'], true)) {
            return;
        }

        // Validacion defensiva: sin un numero de destino real no tiene sentido
        // llamar a n8n/Evolution. Un placeholder (p. ej. 521XXXXXXXXXX) o un
        // valor vacio hace que Evolution responda 400 y ensucie los logs.
        $telefono = $this->normalizarTelefono(config('services.n8n.notify_phone'));

        if ($telefono === null) {
            Log::warning('RF-33: alerta de WhatsApp omitida por numero de destino invalido.', [
                'fase_pieza_id'      => $fasePiezaId,
                'telefono_config'    => config('services.n8n.notify_phone'),
            ]);

            return;
        }

        $fasePieza = FasePieza::with(['pieza.obra', 'fase'])->findOrFail($fasePiezaId);

        SendWhatsAppAlertJob::dispatchSync([
            'obra'             => $fasePieza->pieza?->obra?->nombre_obra ?? $fasePieza->pieza?->obra_id,
            'marca'            => $fasePieza->pieza?->clave_marca_pieza ?? 'Sin marca',
            'fase'             => $fasePieza->fase?->nombre ?? 'Inspección',
            'resultado'        => $resultado,
            'inspector'        => auth()->user()->nombre ?? 'Inspector',
            'observacion'      => $descripcion,
            'fecha'            => FechaLocal::ahora('d/m/Y H:i'),
            'telefono_destino' => $telefono,
        ]);
    }

    /**
     * Normaliza y valida el telefono de destino para WhatsApp Mexico:
     * 521 + 10 digitos (13 digitos en total). Acepta tambien el numero
     * con signos de agrupacion (espacios, +, -, parentesis) y devuelve
     * null si no cumple el formato o es un placeholder con 'X'.
     */
    private function normalizarTelefono(?string $telefono): ?string
    {
        $limpio = preg_replace('/[^0-9]/', '', (string) $telefono);

        // Placeholders tipo 521XXXXXXXXXX se vuelven solo digitos del prefijo.
        if ($limpio === '' || preg_match('/X/i', (string) $telefono)) {
            return null;
        }

        // Formato canonico Mexico: 521 + 10 digitos.
        if (preg_match('/^521\d{10}$/', $limpio)) {
            return $limpio;
        }

        // Acepta un numero de 10 digitos y le antepone la lada movil 521.
        if (preg_match('/^\d{10}$/', $limpio)) {
            return '521' . $limpio;
        }

        return null;
    }
}

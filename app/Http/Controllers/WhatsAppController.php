<?php

namespace App\Http\Controllers;

use App\Support\FechaLocal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    /**
     * Pagina de vinculacion. Solo Jefe de Calidad, aunque la ruta ya este
     * protegida por el middleware de jefatura.
     */
    public function index(): View|RedirectResponse
    {
        if (! auth()->user()->esCalidad()) {
            return redirect()->route('dashboard')
                ->with('error', 'Solo el Jefe de Calidad puede vincular WhatsApp.');
        }

        return view('whatsapp.qr');
    }

    /**
     * Devuelve el QR vigente leyendo Evolution API en el servidor.
     * La API key nunca sale del backend: al navegador solo llega la imagen.
     */
    public function estado(): JsonResponse
    {
        if (! auth()->user()->esCalidad()) {
            return response()->json([
                'estado'  => 'error',
                'mensaje' => 'No autorizado.',
            ], 403);
        }

        $url       = rtrim((string) config('services.evolution.url'), '/');
        $apiKey    = (string) config('services.evolution.api_key', '');
        $instancia = (string) config('services.evolution.instance', 'famep-wa');

        if ($url === '' || $apiKey === '' || $instancia === '') {
            Log::warning('Vinculacion WhatsApp omitida: falta configuracion de Evolution API.');

            return $this->error('Falta la configuracion de WhatsApp. Avise al administrador.');
        }

        try {
            $respuesta = Http::timeout((int) config('services.evolution.timeout', 10))
                ->withHeaders(['apikey' => $apiKey])
                ->acceptJson()
                ->get($url . '/instance/connect/' . $instancia);
        } catch (\Throwable $e) {
            Log::error('No se pudo consultar el QR de WhatsApp.', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('No se pudo contactar con el servicio de WhatsApp. Intente de nuevo.');
        }

        if ($respuesta->failed()) {
            Log::error('Evolution API rechazo la consulta del QR.', [
                'status' => $respuesta->status(),
                'body'   => $respuesta->body(),
            ]);

            return $this->error('El servicio de WhatsApp respondio un error. Intente de nuevo.');
        }

        $datos = $respuesta->json();

        if (! is_array($datos)) {
            return $this->error('El servicio de WhatsApp devolvio una respuesta inesperada.');
        }

        $estadoConexion = $datos['state']
            ?? $datos['instance']['state']
            ?? $datos['connectionStatus']
            ?? $datos['instance']['connectionStatus']
            ?? null;

        if (is_string($estadoConexion) && strtolower($estadoConexion) === 'open') {
            return response()->json([
                'estado'      => 'vinculada',
                'instancia'   => $instancia,
                'mensaje'     => 'WhatsApp ya esta vinculado.',
                'actualizado' => FechaLocal::ahora('H:i:s'),
            ]);
        }

        $qr = $datos['qrcode']
            ?? $datos['base64']
            ?? $datos['instance']['qrcode']
            ?? null;

        if (! is_string($qr) || $qr === '') {
            return $this->error('No se obtuvo un QR valido. Vuelva a intentar.');
        }

        if (! str_starts_with($qr, 'data:')) {
            $qr = 'data:image/png;base64,' . $qr;
        }

        return response()->json([
            'estado'      => 'qr',
            'imagen'      => $qr,
            'instancia'   => $instancia,
            'mensaje'     => 'Escanee el codigo desde WhatsApp.',
            'actualizado' => FechaLocal::ahora('H:i:s'),
        ]);
    }

    private function error(string $mensaje): JsonResponse
    {
        return response()->json([
            'estado'      => 'error',
            'mensaje'     => $mensaje,
            'actualizado' => FechaLocal::ahora('H:i:s'),
        ]);
    }
}

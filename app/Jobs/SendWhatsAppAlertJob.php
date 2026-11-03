<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppAlertJob implements ShouldQueue
{
    use Queueable;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(config('services.n8n.timeout', 15))
                ->acceptJson()
                ->post(config('services.n8n.webhook_url'), $this->payload);

            // Http::post no lanza excepción ante 4xx/5xx: sin esta revisión un
            // rechazo de n8n o de Evolution quedaba silencioso.
            if ($response->failed()) {
                Log::error('n8n rechazo la alerta de WhatsApp', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'payload' => $this->payload,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error enviando alerta de WhatsApp via n8n', [
                'payload' => $this->payload,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
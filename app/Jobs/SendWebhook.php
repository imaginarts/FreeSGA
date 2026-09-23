<?php

namespace App\Jobs;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public int $webhookId, public string $event, public array $payload) {}

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (! $webhook?->enabled) {
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(array_merge($webhook->headers ?? [], ['X-Webhook-Event' => $this->event]))
                ->post($webhook->url, $this->payload);

            if ($response->failed()) {
                Log::warning("Webhook #{$webhook->id} ({$this->event}) respondeu HTTP {$response->status()}");
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            }
        } catch (Throwable $e) {
            Log::warning("Webhook #{$webhook->id} ({$this->event}) falhou: {$e->getMessage()}");
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);
        }
    }
}

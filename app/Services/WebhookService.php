<?php

namespace App\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookService
{
    public function deliver(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->loadMissing([
            'application:id,code,webhook_secret',
            'paymentOrder:id,public_id',
        ]);

        $body = json_encode($delivery->request_body ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body ?: '{}', (string) $delivery->application?->webhook_secret);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $delivery->event_type,
                    'X-Webhook-Delivery-Id' => $delivery->public_id,
                    'X-Webhook-Timestamp' => $timestamp,
                    'User-Agent' => 'PaymentHub/1.0',
                ])
                ->withBody($body ?: '{}', 'application/json')
                ->post($delivery->target_url);

            $success = $response->successful();

            $delivery->forceFill([
                'status' => $success ? WebhookDeliveryStatus::Success : WebhookDeliveryStatus::Failed,
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'next_retry_at' => $success ? null : $this->nextRetryAt($delivery->attempt),
            ])->save();

            $this->recordEvent(
                $delivery,
                $success ? 'webhook.success' : 'webhook.failed',
                [
                    'delivery_id' => $delivery->public_id,
                    'event' => $delivery->event_type,
                    'target_url' => $delivery->target_url,
                    'response_code' => $response->status(),
                ],
            );
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'response_code' => null,
                'response_body' => $exception->getMessage(),
                'next_retry_at' => $this->nextRetryAt($delivery->attempt),
            ])->save();

            $this->recordEvent($delivery, 'webhook.failed', [
                'delivery_id' => $delivery->public_id,
                'event' => $delivery->event_type,
                'target_url' => $delivery->target_url,
                'response_code' => null,
                'error' => $exception->getMessage(),
            ]);
        }

        return $delivery->fresh();
    }

    protected function recordEvent(WebhookDelivery $delivery, string $eventType, array $payload): void
    {
        $payment = $delivery->paymentOrder;

        if (! $payment) {
            return;
        }

        $payment->paymentEvents()->create([
            'public_id' => 'evt_' . str()->lower((string) str()->ulid()),
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    protected function nextRetryAt(int $attempt): ?\Illuminate\Support\Carbon
    {
        return match ($attempt) {
            1 => now()->addMinute(),
            2 => now()->addMinutes(5),
            3 => now()->addMinutes(30),
            4 => now()->addHours(2),
            5 => now()->addHours(12),
            default => null,
        };
    }
}

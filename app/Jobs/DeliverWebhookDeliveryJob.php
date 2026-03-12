<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly int $deliveryId)
    {
        $this->afterCommit();
    }

    public function handle(WebhookService $webhookService): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if (! $delivery || $delivery->status !== WebhookDeliveryStatus::Pending) {
            return;
        }

        $webhookService->deliver($delivery);
    }
}

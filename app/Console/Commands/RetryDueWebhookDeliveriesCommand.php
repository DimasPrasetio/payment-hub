<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class RetryDueWebhookDeliveriesCommand extends Command
{
    protected $signature = 'webhook-deliveries:retry-due {--limit=100 : Maximum due deliveries to queue per run}';

    protected $description = 'Queue due webhook delivery retries based on next_retry_at.';

    public function __construct(private readonly PaymentService $paymentService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $queued = $this->paymentService->dispatchDueWebhookRetries(
            max(1, (int) $this->option('limit')),
        );

        $this->info(sprintf('Queued %d webhook delivery retr%s.', $queued, $queued === 1 ? 'y' : 'ies'));

        return self::SUCCESS;
    }
}

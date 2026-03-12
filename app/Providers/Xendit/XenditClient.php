<?php

namespace App\Providers\Xendit;

use App\Models\PaymentProvider;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Refund\CreateRefund;
use Xendit\Refund\RefundApi;

class XenditClient
{
    public function createInvoice(PaymentProvider $provider, array $payload): mixed
    {
        $this->configure($provider);

        return (new InvoiceApi())->createInvoice(new CreateInvoiceRequest($payload));
    }

    public function getInvoicesByExternalId(PaymentProvider $provider, string $externalId): array
    {
        $this->configure($provider);

        return (new InvoiceApi())->getInvoices(null, $externalId);
    }

    public function createRefund(PaymentProvider $provider, string $idempotencyKey, array $payload): mixed
    {
        $this->configure($provider);

        return (new RefundApi())->createRefund($idempotencyKey, null, new CreateRefund($payload));
    }

    protected function configure(PaymentProvider $provider): void
    {
        Configuration::setXenditKey((string) data_get($provider->config, 'secret_key'));
    }
}

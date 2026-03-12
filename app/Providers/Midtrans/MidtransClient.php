<?php

namespace App\Providers\Midtrans;

use App\Models\PaymentProvider;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransClient
{
    public function createTransaction(PaymentProvider $provider, array $payload, ?string $idempotencyKey = null, ?string $notificationUrl = null): object
    {
        $this->configure($provider, $idempotencyKey, $notificationUrl);

        return Snap::createTransaction($payload);
    }

    public function queryTransaction(PaymentProvider $provider, string $merchantRef): object
    {
        $this->configure($provider);

        return Transaction::status($merchantRef);
    }

    public function refund(PaymentProvider $provider, string $merchantRef, array $payload): object
    {
        $this->configure($provider);

        return Transaction::refund($merchantRef, $payload);
    }

    protected function configure(PaymentProvider $provider, ?string $idempotencyKey = null, ?string $notificationUrl = null): void
    {
        Config::$serverKey = (string) data_get($provider->config, 'server_key');
        Config::$clientKey = (string) data_get($provider->config, 'client_key');
        Config::$isProduction = ! $provider->sandbox_mode;
        Config::$isSanitized = true;
        Config::$is3ds = true;
        Config::$appendNotifUrl = null;
        Config::$overrideNotifUrl = $notificationUrl;
        Config::$paymentIdempotencyKey = $idempotencyKey;
        Config::$curlOptions = [];
    }
}

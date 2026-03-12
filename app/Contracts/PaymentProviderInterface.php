<?php

namespace App\Contracts;

use App\Models\PaymentMethodMapping;
use App\Models\PaymentOrder;
use App\Models\PaymentProvider;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    public function createTransaction(PaymentOrder $payment, PaymentMethodMapping $mapping, PaymentProvider $provider): array;

    public function queryTransaction(PaymentOrder $payment, PaymentProvider $provider): array;

    public function verifyCallback(Request $request, PaymentProvider $provider): array;

    public function getAvailablePaymentMethods(PaymentProvider $provider): array;

    public function refund(PaymentOrder $payment, int $amount, string $reason, PaymentProvider $provider): array;
}

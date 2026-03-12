<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Exceptions\ApiException;
use App\Providers\Midtrans\MidtransProvider;
use App\Providers\Tripay\TripayProvider;
use App\Providers\Xendit\XenditProvider;

class ProviderResolver
{
    public function resolve(string $providerCode): PaymentProviderInterface
    {
        return match (strtolower($providerCode)) {
            'tripay' => app(TripayProvider::class),
            'midtrans' => app(MidtransProvider::class),
            'xendit' => app(XenditProvider::class),
            default => throw new ApiException(
                'PROVIDER_NOT_FOUND',
                'Provider code is not recognized.',
                422,
            ),
        };
    }
}

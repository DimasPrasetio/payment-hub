<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentProviderSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            [
                'code' => 'tripay',
                'name' => 'Tripay',
                'defaults' => [
                    'api_base_url' => 'https://tripay.co.id/api-sandbox',
                    'public_base_url' => 'https://tripay.co.id',
                    'supports_refund_api' => false,
                ],
            ],
            [
                'code' => 'midtrans',
                'name' => 'Midtrans',
                'defaults' => [
                    'api_base_url' => 'https://api.sandbox.midtrans.com',
                    'public_base_url' => 'https://app.sandbox.midtrans.com',
                    'supports_refund_api' => true,
                ],
            ],
            [
                'code' => 'xendit',
                'name' => 'Xendit',
                'defaults' => [
                    'api_base_url' => 'https://api.xendit.co',
                    'public_base_url' => 'https://checkout.xendit.co',
                    'supports_refund_api' => true,
                ],
            ],
        ])->each(function (array $definition) {
            $provider = PaymentProvider::query()->firstOrNew([
                'code' => $definition['code'],
            ]);

            $provider->name = $definition['name'];
            $provider->sandbox_mode = $provider->exists ? $provider->sandbox_mode : true;
            $provider->is_active = $provider->exists ? $provider->is_active : false;
            $provider->config = array_replace($definition['defaults'], $provider->config ?? []);
            $provider->save();
        });
    }
}

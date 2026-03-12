<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtolower((string) $this->input('code', '')),
            'name' => trim((string) $this->input('name', '')),
            'merchant_code' => trim((string) $this->input('merchant_code', '')),
            'api_key' => trim((string) $this->input('api_key', '')),
            'private_key' => trim((string) $this->input('private_key', '')),
            'client_key' => trim((string) $this->input('client_key', '')),
            'server_key' => trim((string) $this->input('server_key', '')),
            'secret_key' => trim((string) $this->input('secret_key', '')),
            'callback_token' => trim((string) $this->input('callback_token', '')),
            'api_base_url' => trim((string) $this->input('api_base_url', '')),
            'public_base_url' => trim((string) $this->input('public_base_url', '')),
            'return_url' => trim((string) $this->input('return_url', '')),
            'extra_config' => trim((string) $this->input('extra_config', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::exists('payment_providers', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sandbox_mode' => ['nullable', 'boolean'],
            'merchant_code' => ['nullable', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'private_key' => ['nullable', 'string', 'max:255'],
            'client_key' => ['nullable', 'string', 'max:255'],
            'server_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'callback_token' => ['nullable', 'string', 'max:255'],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'public_base_url' => ['nullable', 'url', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:255'],
            'supports_refund_api' => ['nullable', 'boolean'],
            'extra_config' => ['nullable', 'json'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('is_active')) {
                return;
            }

            /** @var PaymentProvider|null $provider */
            $provider = $this->route('provider');
            $code = strtolower((string) ($this->input('code') ?: $provider?->code));
            $requirements = $this->activationRequirements()[$code] ?? [];

            foreach ($requirements as $field => $label) {
                $submitted = trim((string) $this->input($field, ''));
                $existing = trim((string) data_get($provider?->config, $field, ''));

                if ($submitted === '' && $existing === '') {
                    $validator->errors()->add($field, "{$label} wajib diisi sebelum provider diaktifkan.");
                }
            }
        });
    }

    protected function activationRequirements(): array
    {
        return [
            'tripay' => [
                'merchant_code' => 'Merchant Code',
                'api_key' => 'API Key',
                'private_key' => 'Private Key',
            ],
            'midtrans' => [
                'server_key' => 'Server Key',
            ],
            'xendit' => [
                'secret_key' => 'Secret Key',
                'callback_token' => 'Callback Token',
            ],
        ];
    }
}

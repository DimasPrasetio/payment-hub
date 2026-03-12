<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class CreatePaymentRequest extends ClientAppRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'application_code' => $this->filled('application_code')
                ? strtoupper((string) $this->input('application_code'))
                : null,
            'currency' => strtoupper((string) $this->input('currency', 'IDR')),
            'payment_method' => strtoupper((string) $this->input('payment_method', '')),
            'provider_code' => $this->filled('provider_code') ? strtolower((string) $this->input('provider_code')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'application_code' => ['nullable', 'string', 'max:20'],
            'external_order_id' => ['required', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['IDR'])],
            'payment_method' => ['required', 'string', 'max:50'],
            'provider_code' => ['nullable', 'string', 'max:50'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:100'],
            'customer.email' => ['required', 'email', 'max:150'],
            'customer.phone' => ['required', 'regex:/^628[0-9]{7,15}$/'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateApplicationCode($validator);
    }
}

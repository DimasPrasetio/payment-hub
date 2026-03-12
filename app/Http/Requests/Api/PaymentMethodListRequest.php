<?php

namespace App\Http\Requests\Api;

class PaymentMethodListRequest extends ClientAppRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_code' => $this->filled('provider_code') ? strtolower((string) $this->input('provider_code')) : null,
            'active_only' => filter_var($this->input('active_only', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'group' => $this->filled('group') ? strtolower((string) $this->input('group')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'provider_code' => ['nullable', 'exists:payment_providers,code'],
            'active_only' => ['nullable', 'boolean'],
            'group' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

class PaymentLookupRequest extends ClientAppRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'application_code' => $this->filled('application_code')
                ? strtoupper((string) $this->input('application_code'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'application_code' => ['nullable', 'string', 'max:20'],
            'external_order_id' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateApplicationCode($validator);
    }
}

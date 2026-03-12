<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentOrderStatus;
use Illuminate\Validation\Rule;

class PaymentListRequest extends ClientAppRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'application_code' => $this->filled('application_code') ? strtoupper((string) $this->input('application_code')) : null,
            'provider_code' => $this->filled('provider_code') ? strtolower((string) $this->input('provider_code')) : null,
            'payment_method' => $this->filled('payment_method') ? strtoupper((string) $this->input('payment_method')) : null,
            'status' => $this->filled('status') ? strtoupper((string) $this->input('status')) : null,
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_order' => strtolower((string) $this->input('sort_order', 'desc')),
            'per_page' => (int) $this->input('per_page', 20),
        ]);
    }

    public function rules(): array
    {
        return [
            'application_code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', Rule::in(PaymentOrderStatus::values())],
            'provider_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'amount', 'status'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateApplicationCode($validator);
    }
}

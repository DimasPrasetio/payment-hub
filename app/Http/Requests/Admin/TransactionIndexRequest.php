<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'application' => ['nullable', 'exists:applications,code'],
            'provider' => ['nullable', 'exists:payment_providers,code'],
            'status' => ['nullable', Rule::in(PaymentOrderStatus::values())],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconciliationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application' => ['nullable', 'exists:applications,code'],
            'provider' => ['nullable', 'exists:payment_providers,code'],
            'issue' => ['nullable', Rule::in([
                'missing_provider_transaction',
                'missing_successful_webhook',
                'paid_without_paid_event',
                'expired_but_still_pending',
            ])],
        ];
    }
}

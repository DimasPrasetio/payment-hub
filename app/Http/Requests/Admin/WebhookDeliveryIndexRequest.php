<?php

namespace App\Http\Requests\Admin;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookDeliveryIndexRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(WebhookDeliveryStatus::values())],
            'event_type' => ['nullable', 'string', 'max:50'],
            'payment' => ['nullable', 'exists:payment_orders,public_id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}

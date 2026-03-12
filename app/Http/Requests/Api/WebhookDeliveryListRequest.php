<?php

namespace App\Http\Requests\Api;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Validation\Rule;

class WebhookDeliveryListRequest extends ClientAppRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => (int) $this->input('per_page', 20),
        ]);
    }

    public function rules(): array
    {
        return [
            'payment_id' => ['nullable', 'string', 'max:30'],
            'event_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(WebhookDeliveryStatus::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

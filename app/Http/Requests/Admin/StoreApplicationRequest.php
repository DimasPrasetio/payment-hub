<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code', ''))),
            'name' => trim((string) $this->input('name', '')),
            'webhook_url' => trim((string) $this->input('webhook_url', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('applications', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'default_provider' => ['required', Rule::exists('payment_providers', 'code')->where('is_active', true)],
            'webhook_url' => ['required', 'url', 'max:500'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}

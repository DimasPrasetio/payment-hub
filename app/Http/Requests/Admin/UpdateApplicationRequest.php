<?php

namespace App\Http\Requests\Admin;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'webhook_url' => trim((string) $this->input('webhook_url', '')),
        ]);
    }

    public function rules(): array
    {
        /** @var Application|null $application */
        $application = $this->route('application');

        return [
            'name' => ['required', 'string', 'max:100'],
            'default_provider' => ['required', Rule::exists('payment_providers', 'code')->where('is_active', true)],
            'webhook_url' => ['required', 'url', 'max:500'],
            'status' => ['nullable', 'boolean'],
            'code' => ['nullable', 'string', 'max:20', Rule::in([$application?->code])],
        ];
    }
}

<?php

namespace App\Http\Requests\Api;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ClientAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function clientApplication(): ?Application
    {
        $application = $this->attributes->get('client_application');

        return $application instanceof Application ? $application : null;
    }

    protected function validateApplicationCode(Validator $validator, string $field = 'application_code'): void
    {
        $validator->after(function (Validator $validator) use ($field) {
            $application = $this->clientApplication();
            $applicationCode = $this->input($field);

            if (! $application || ! is_string($applicationCode) || $applicationCode === '') {
                return;
            }

            if ($applicationCode !== $application->code) {
                $validator->errors()->add($field, 'The application_code must match the authenticated application.');
            }
        });
    }
}

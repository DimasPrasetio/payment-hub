<?php

namespace App\Http\Requests\Api;

class RefundPaymentRequest extends ClientAppRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

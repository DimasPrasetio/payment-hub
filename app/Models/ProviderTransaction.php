<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_order_id',
        'provider',
        'merchant_ref',
        'provider_reference',
        'payment_method',
        'payment_url',
        'pay_code',
        'qr_string',
        'qr_url',
        'raw_request',
        'raw_response',
        'paid_at',
    ];

    protected $casts = [
        'raw_request' => 'array',
        'raw_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider', 'code');
    }
}

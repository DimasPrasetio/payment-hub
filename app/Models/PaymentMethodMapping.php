<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_code',
        'provider_code',
        'provider_method_code',
        'display_name',
        'group',
        'icon_url',
        'fee_flat',
        'fee_percent',
        'min_amount',
        'max_amount',
        'is_active',
    ];

    protected $casts = [
        'fee_flat' => 'integer',
        'fee_percent' => 'decimal:2',
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'is_active' => 'boolean',
    ];

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

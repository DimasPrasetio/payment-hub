<?php

namespace App\Models;

use App\Enums\PaymentOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'application_id',
        'tenant_id',
        'external_order_id',
        'idempotency_key',
        'merchant_ref',
        'provider_code',
        'payment_method',
        'customer_name',
        'customer_email',
        'customer_phone',
        'amount',
        'currency',
        'status',
        'metadata',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'status' => PaymentOrderStatus::class,
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'provider_code', 'code');
    }

    public function providerTransactions(): HasMany
    {
        return $this->hasMany(ProviderTransaction::class);
    }

    public function latestProviderTransaction(): HasOne
    {
        return $this->hasOne(ProviderTransaction::class)->latestOfMany();
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeLatestFirst($query)
    {
        return $query->latest('created_at');
    }
}

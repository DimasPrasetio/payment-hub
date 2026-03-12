<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'public_id',
        'payment_order_id',
        'application_id',
        'event_type',
        'target_url',
        'request_body',
        'response_code',
        'response_body',
        'attempt',
        'status',
        'next_retry_at',
        'created_at',
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_code' => 'integer',
        'attempt' => 'integer',
        'status' => WebhookDeliveryStatus::class,
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }
}

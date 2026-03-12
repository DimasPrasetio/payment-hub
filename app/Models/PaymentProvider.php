<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class PaymentProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'config',
        'is_active',
        'sandbox_mode',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sandbox_mode' => 'boolean',
    ];

    public function paymentMethodMappings(): HasMany
    {
        return $this->hasMany(PaymentMethodMapping::class, 'provider_code', 'code');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'default_provider', 'code');
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class, 'provider_code', 'code');
    }

    public function providerTransactions(): HasMany
    {
        return $this->hasMany(ProviderTransaction::class, 'provider', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected function config(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                if ($value === null || $value === '') {
                    return [];
                }

                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }

                try {
                    $decrypted = Crypt::decryptString($value);
                    $decoded = json_decode($decrypted, true);

                    return is_array($decoded) ? $decoded : [];
                } catch (Throwable) {
                    return [];
                }
            },
            set: function (mixed $value): ?string {
                if ($value === null || $value === [] || $value === '') {
                    return null;
                }

                $payload = is_array($value) ? $value : (array) $value;

                return Crypt::encryptString(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            },
        );
    }
}

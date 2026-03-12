<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Throwable;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'api_key',
        'default_provider',
        'webhook_url',
        'webhook_secret',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'webhook_secret',
    ];

    public function defaultProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class, 'default_provider', 'code');
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    protected function webhookSecret(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                try {
                    return $this->decryptWebhookSecret($value);
                } catch (Throwable) {
                    return $value;
                }
            },
            set: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                return $this->encryptWebhookSecret($value);
            },
        );
    }

    protected function encryptWebhookSecret(string $value): string
    {
        $key = $this->webhookSecretKey();
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt webhook secret.');
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        return 'enc:' . base64_encode($iv . $mac . $ciphertext);
    }

    protected function decryptWebhookSecret(string $value): string
    {
        if (! Str::startsWith($value, 'enc:')) {
            return $value;
        }

        $decoded = base64_decode(substr($value, 4), true);

        if ($decoded === false || strlen($decoded) < 49) {
            throw new \RuntimeException('Invalid webhook secret payload.');
        }

        $iv = substr($decoded, 0, 16);
        $mac = substr($decoded, 16, 32);
        $ciphertext = substr($decoded, 48);
        $key = $this->webhookSecretKey();
        $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        if (! hash_equals($expectedMac, $mac)) {
            throw new \RuntimeException('Webhook secret integrity check failed.');
        }

        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if (! is_string($decrypted)) {
            throw new \RuntimeException('Unable to decrypt webhook secret.');
        }

        return $decrypted;
    }

    protected function webhookSecretKey(): string
    {
        $key = (string) config('app.key', '');

        if (Str::startsWith($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return hash('sha256', $decoded, true);
            }
        }

        return hash('sha256', $key, true);
    }
}

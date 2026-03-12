<?php

namespace App\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function tone(): string
    {
        return match ($this) {
            self::Success => 'emerald',
            self::Pending => 'amber',
            self::Failed => 'rose',
        };
    }

    public static function values(): array
    {
        return array_map(
            static fn (self $status) => $status->value,
            self::cases(),
        );
    }
}

<?php

namespace App\Enums;

enum PaymentOrderStatus: string
{
    case Created = 'CREATED';
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Refunded = 'REFUNDED';

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'emerald',
            self::Created, self::Pending => 'amber',
            self::Failed => 'rose',
            self::Expired => 'orange',
            self::Refunded => 'violet',
        };
    }

    public function isProblem(): bool
    {
        return in_array($this, [self::Failed, self::Expired], true);
    }

    public static function values(): array
    {
        return array_map(
            static fn (self $status) => $status->value,
            self::cases(),
        );
    }
}

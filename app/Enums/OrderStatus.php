<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu bayaran',
            self::Paid => 'Dibayar',
            self::Shipped => 'Dihantar',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Shipped => 'blue',
            self::Cancelled => 'zinc',
        };
    }

    public function canTransitionTo(self $to, bool $requiresShipping): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Paid, self::Cancelled], true),
            self::Paid => $to === self::Cancelled || ($to === self::Shipped && $requiresShipping),
            default => false,
        };
    }
}

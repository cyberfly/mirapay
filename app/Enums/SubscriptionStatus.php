<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** Subscribed, first payment not received yet. */
    case Pending = 'pending';
    case Active = 'active';
    /** A renewal is unpaid; access continues during the grace period. */
    case PastDue = 'past_due';
    case Paused = 'paused';
    /** Stops renewing; access lasts until the paid period ends. */
    case Cancelled = 'cancelled';
    /** Renewal stayed unpaid past the grace period. */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu bayaran',
            self::Active => 'Aktif',
            self::PastDue => 'Bayaran tertunggak',
            self::Paused => 'Dijeda',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Tamat tempoh',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'green',
            self::PastDue => 'red',
            self::Paused => 'blue',
            self::Cancelled, self::Expired => 'zinc',
        };
    }
}

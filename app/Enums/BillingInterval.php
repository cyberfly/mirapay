<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Bulanan',
            self::Year => 'Tahunan',
        };
    }

    /**
     * The unit shown after a price, e.g. "RM29.00 / bulan".
     */
    public function unit(): string
    {
        return match ($this) {
            self::Month => 'bulan',
            self::Year => 'tahun',
        };
    }

    public function addTo(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Month => $date->addMonthNoOverflow(),
            self::Year => $date->addYearNoOverflow(),
        };
    }
}

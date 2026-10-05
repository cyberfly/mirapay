<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use Throwable;

final readonly class ReportRange
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(?string $from = null, ?string $to = null)
    {
        $end = self::parse($to)?->endOfDay() ?? CarbonImmutable::now()->endOfDay();
        $start = self::parse($from)?->startOfDay() ?? $end->subDays(29)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $this->from = $start;
        $this->to = $end;
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', trim($value)) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}

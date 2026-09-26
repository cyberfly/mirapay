<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function format(int $sen): string
    {
        $sign = $sen < 0 ? '-' : '';
        $sen = abs($sen);

        return $sign.'RM'.number_format(intdiv($sen, 100)).'.'.str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function toSen(string $value): int
    {
        $value = str_replace([',', ' '], '', trim($value));

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException("Jumlah wang tidak sah: {$value}");
        }

        return ((int) $matches[1]) * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    public static function toInput(int $sen): string
    {
        return intdiv($sen, 100).'.'.str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
    }
}

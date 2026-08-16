<?php

namespace App\Support;

final class Money
{
    public static function toMinor(float|int|string $amount, string $currency = 'SAR'): int
    {
        $factor = self::minorFactor($currency);

        return (int) round(((float) $amount) * $factor);
    }

    public static function fromMinor(int $amountMinor, string $currency = 'SAR'): string
    {
        $factor = self::minorFactor($currency);

        return number_format($amountMinor / $factor, self::minorDigits($currency), '.', '');
    }

    public static function minorFactor(string $currency): int
    {
        return 10 ** self::minorDigits($currency);
    }

    private static function minorDigits(string $currency): int
    {
        return strtoupper($currency) === 'KWD' ? 3 : (strtoupper($currency) === 'JPY' ? 0 : 2);
    }
}

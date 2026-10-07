<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** Paystack amounts are in the currency's subunit (kobo for NGN). Never use floats for money maths. */
final class Money
{
    public static function toKobo(string|int|float $naira): int
    {
        $value = trim((string) $naira);
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Invalid amount: $value");
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $kobo = ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
        if ($kobo < 100) {
            throw new InvalidArgumentException('Minimum amount is ₦1.00');
        }
        return $kobo;
    }

    public static function format(int $kobo, string $currency = 'NGN'): string
    {
        $symbol = ['NGN' => '₦', 'GHS' => 'GH₵', 'ZAR' => 'R', 'USD' => '$', 'KES' => 'KSh'][$currency] ?? '';
        return $symbol . number_format($kobo / 100, 2);
    }
}

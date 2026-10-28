<?php

namespace App\Support;

/**
 * Philippine VAT split for a VAT-INCLUSIVE receipt amount.
 *
 *   VAT supplier      : net = gross / 1.12, input tax = gross - net (12/112 of gross)
 *   Non-VAT supplier  : no input tax; net = gross
 *
 * "Non-VAT" covers non-VAT-registered suppliers and any VAT-exempt or
 * zero-rated purchase (the ERD only distinguishes VAT / Non-VAT) — none of
 * those carry creditable input tax.
 *
 * Works in whole centavos and rounds the tax once, so net + tax always equals
 * gross exactly (no float drift, no stray centavo).
 */
final class PhVat
{
    public const VAT = 'VAT';
    public const NON_VAT = 'NON_VAT';

    public static function natures(): array
    {
        return [self::VAT => 'VAT (VAT-registered supplier)', self::NON_VAT => 'Non-VAT'];
    }

    public static function rate(): float
    {
        return (float) config('cams.vat_rate', 12.0);
    }

    /** @return array{net: string, input_tax: string, rate: string} */
    public static function split(float|int|string $gross, string $nature): array
    {
        $cents = (int) round(((float) $gross) * 100);
        $rate = $nature === self::VAT ? self::rate() : 0.0;
        $taxCents = $nature === self::VAT ? (int) round($cents * $rate / (100 + $rate)) : 0;

        return [
            'net' => number_format(($cents - $taxCents) / 100, 2, '.', ''),
            'input_tax' => number_format($taxCents / 100, 2, '.', ''),
            'rate' => number_format($rate, 2, '.', ''),
        ];
    }

    /**
     * BIR TIN: 9 digits + 3-5 digit branch code, dashes optional
     * (e.g. 123-456-789-000).
     */
    public const TIN_PATTERN = '/^\d{3}-?\d{3}-?\d{3}(-?\d{3,5})?$/';
}

<?php

namespace Espo\Modules\StudioManagement\Tools;

use InvalidArgumentException;

final class MoneyCalculator
{
    private const PERCENT_SCALE = 10000;
    private const PERCENT_DENOMINATOR = 100 * self::PERCENT_SCALE;
    private const MAX_CENTS = 100_000_000_000;

    public function amountToCents(string|int|float $amount): int
    {
        $normalized = str_replace(',', '.', trim((string) $amount));

        if (!preg_match('/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Amount must be a positive number with at most two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        if ($cents < 0 || $cents > self::MAX_CENTS) {
            throw new InvalidArgumentException('Amount is outside the supported range.');
        }

        return $cents;
    }

    public function percentToUnits(string|int|float|null $percent): int
    {
        $normalized = str_replace(',', '.', trim((string) ($percent ?? 0)));

        if (!preg_match('/^(?:0|[1-9]\d?|100)(?:\.\d{1,4})?$/', $normalized)) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100 with at most four decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $units = ((int) $whole * self::PERCENT_SCALE) + (int) str_pad($fraction, 4, '0');

        if ($units > self::PERCENT_DENOMINATOR) {
            throw new InvalidArgumentException('Percentage cannot exceed 100.');
        }

        return $units;
    }

    public function share(int $amountCents, int $percentUnits): int
    {
        return intdiv(
            ($amountCents * $percentUnits) + intdiv(self::PERCENT_DENOMINATOR, 2),
            self::PERCENT_DENOMINATOR
        );
    }

    public function centsToAmount(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function unitsToPercent(int $units): string
    {
        $whole = intdiv($units, self::PERCENT_SCALE);
        $fraction = rtrim(str_pad((string) ($units % self::PERCENT_SCALE), 4, '0', STR_PAD_LEFT), '0');

        return $fraction === '' ? (string) $whole : $whole . '.' . $fraction;
    }
}

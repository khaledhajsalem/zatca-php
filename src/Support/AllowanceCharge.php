<?php

namespace KhaledHajSalem\Zatca\Support;

use InvalidArgumentException;

/**
 * Shared rules for allowances and charges, at document level (BG-20, BG-21)
 * and at invoice line level (BG-27, BG-28).
 */
class AllowanceCharge
{
    /**
     * Validate base amount / percentage and round the amounts.
     *
     * - `base_amount` and `multiplier` (a percentage, 0-100 with at most two
     *   decimals) must be given together (BR-KSA-EN16931-04/05, BR-KSA-DEC-01).
     * - `amount` is computed from them when omitted, and must match them
     *   when given (BR-KSA-EN16931-03).
     *
     * @param array<string, mixed> $allowanceCharge
     * @return array<string, mixed>
     * @throws InvalidArgumentException For inconsistent values.
     */
    public static function normalize(array $allowanceCharge): array
    {
        $hasBase = isset($allowanceCharge['base_amount']);
        $hasMultiplier = isset($allowanceCharge['multiplier']);

        if ($hasBase !== $hasMultiplier) {
            throw new InvalidArgumentException(
                "Allowance/charge 'base_amount' and 'multiplier' must be provided together (BR-KSA-EN16931-04/05)."
            );
        }

        if ($hasBase) {
            $multiplier = (float)$allowanceCharge['multiplier'];

            if ($multiplier < 0 || $multiplier > 100 || Money::round($multiplier) !== $multiplier) {
                throw new InvalidArgumentException(
                    "Allowance/charge 'multiplier' must be a percentage from 0 to 100 with at most two decimals (BR-KSA-DEC-01)."
                );
            }

            $allowanceCharge['base_amount'] = Money::round((float)$allowanceCharge['base_amount']);
            $expected = Money::round($allowanceCharge['base_amount'] * $multiplier / 100);

            if (!isset($allowanceCharge['amount'])) {
                $allowanceCharge['amount'] = $expected;
            } elseif (Money::round((float)$allowanceCharge['amount']) !== $expected) {
                throw new InvalidArgumentException(
                    "Allowance/charge 'amount' must equal base_amount × multiplier / 100 ($expected) (BR-KSA-EN16931-03)."
                );
            }
        }

        $allowanceCharge['amount'] = Money::round((float)($allowanceCharge['amount'] ?? 0.0));

        return $allowanceCharge;
    }
}

<?php

namespace KhaledHajSalem\Zatca\Support;

use InvalidArgumentException;

/**
 * ZATCA VAT category codes (UNCL5305 subset, BR-KSA-18) and the
 * VAT exemption / zero-rating reason codes (BR-KSA-69, BR-KSA-83).
 */
class TaxCategory
{
    /** Standard rated (15%). */
    public const STANDARD = 'S';

    /** Zero rated goods and services. */
    public const ZERO_RATED = 'Z';

    /** Exempt from VAT. */
    public const EXEMPT = 'E';

    /** Services outside the scope of VAT. */
    public const OUT_OF_SCOPE = 'O';

    /**
     * Allowed exemption reason codes per category, with ZATCA's reason text.
     * A null text means ZATCA expects free text from the taxpayer.
     */
    private const REASONS = [
        self::EXEMPT => [
            'VATEX-SA-29'   => 'Financial services mentioned in Article 29 of the VAT Regulations',
            'VATEX-SA-29-7' => 'Life insurance services mentioned in Article 29 of the VAT Regulations',
            'VATEX-SA-30'   => 'Real estate transactions mentioned in Article 30 of the VAT Regulations',
        ],
        self::ZERO_RATED => [
            'VATEX-SA-32'    => 'Export of goods',
            // Added by ZATCA SDK 3.4.2.
            'VATEX-SA-32(bis)' => 'Supply under Customs Suspension Arrangement',
            'VATEX-SA-33'    => 'Export of services',
            'VATEX-SA-34-1'  => 'The international transport of Goods',
            'VATEX-SA-34-2'  => 'international transport of passengers',
            'VATEX-SA-34-3'  => 'services directly connected and incidental to a Supply of international passenger transport',
            'VATEX-SA-34-4'  => 'Supply of a qualifying means of transport',
            'VATEX-SA-34-5'  => 'Any services relating to Goods or passenger transportation, as defined in article twenty five of these Regulations',
            'VATEX-SA-35'    => 'Medicines and medical equipment',
            'VATEX-SA-36'    => 'Qualifying metals',
            'VATEX-SA-EDU'   => 'Private education to citizen',
            'VATEX-SA-HEA'   => 'Private healthcare to citizen',
            'VATEX-SA-MLTRY' => 'supply of qualified military goods',
            // Added by ZATCA SDK 3.3.4.
            'VATEX-SA-DUTYFREE' => 'Qualified Supply of Goods in Duty Free area',
            // Added by ZATCA SDK 3.4.2.
            'VATEX-SA-ROYALDECREE' => 'Supply on which the Government bears the VAT',
        ],
        self::OUT_OF_SCOPE => [
            'VATEX-SA-OOS' => null,
        ],
    ];

    /**
     * All supported category codes.
     */
    public static function codes(): array
    {
        return [self::STANDARD, self::ZERO_RATED, self::EXEMPT, self::OUT_OF_SCOPE];
    }

    /**
     * Whether the category requires an exemption reason (Z, E, O).
     */
    public static function requiresExemptionReason(string $category): bool
    {
        return $category !== self::STANDARD;
    }

    /**
     * VAT rates ZATCA accepts for the standard-rated category (BR-KSA-84).
     * Any other rate is rejected with an error.
     */
    public const STANDARD_RATES = [5.0, 15.0];

    /**
     * Whether a VAT rate (cbc:Percent) is emitted for the category.
     *
     * Always true since 1.2.0. Out-of-scope supplies used to omit the rate
     * (BR-O-05), but ZATCA replaced that rule with "the rate shall be 0 if it
     * exists" (BR-KSA-11/12/13) and its validator raises a BR-CO-17 warning
     * when the VAT breakdown of an "O" category has no rate. Verified against
     * the sandbox: "O" with 0.00 passes without warnings.
     */
    public static function hasPercent(string $category): bool
    {
        return true;
    }

    /**
     * Whether the rate is one ZATCA accepts for a standard-rated supply.
     */
    public static function isStandardRate(float $percent): bool
    {
        return in_array($percent, self::STANDARD_RATES, true);
    }

    /**
     * Exemption reason codes allowed for a category, with ZATCA's reason
     * text (null where ZATCA expects free text). Empty for "S".
     *
     * @return array<string, ?string>
     */
    public static function reasons(string $category): array
    {
        return self::REASONS[$category] ?? [];
    }

    /**
     * Validate a category / reason combination and resolve the reason text.
     *
     * @return array{0: ?string, 1: ?string} [reasonCode, reasonText]
     * @throws InvalidArgumentException
     */
    public static function resolve(string $category, ?string $reasonCode, ?string $reasonText): array
    {
        if (!in_array($category, self::codes(), true)) {
            throw new InvalidArgumentException(
                "Invalid VAT category '$category'. Valid options: " . implode(', ', self::codes())
            );
        }

        if (!self::requiresExemptionReason($category)) {
            return [null, null];
        }

        $allowed = self::REASONS[$category];

        if ($reasonCode === null || !array_key_exists($reasonCode, $allowed)) {
            throw new InvalidArgumentException(
                "VAT category '$category' requires an exemption reason code. Valid options: "
                . implode(', ', array_keys($allowed))
            );
        }

        $reasonText = $reasonText ?? $allowed[$reasonCode];

        if ($reasonText === null || trim($reasonText) === '') {
            throw new InvalidArgumentException("Exemption reason code '$reasonCode' requires a reason text.");
        }

        return [$reasonCode, $reasonText];
    }
}

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
     * Whether a VAT rate (cbc:Percent) is emitted for the category.
     * Out-of-scope supplies carry no rate (BR-O-05, BR-O-09).
     */
    public static function hasPercent(string $category): bool
    {
        return $category !== self::OUT_OF_SCOPE;
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

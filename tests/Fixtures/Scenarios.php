<?php

namespace KhaledHajSalem\Zatca\Tests\Fixtures;

use Closure;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Data\SellerData;

/**
 * Every kind of document the library is meant to produce, one builder per
 * scenario: all document types, transaction flags, VAT categories,
 * allowances and charges, prepayments, currencies and optional fields.
 *
 * The same list drives two suites:
 * - ScenarioMatrixTest (always run): each scenario must pass the local
 *   validator without issues, generate, sign and hash consistently.
 * - Sandbox\SandboxComplianceTest (opt-in): each scenario is submitted to
 *   ZATCA's developer-portal validator and must come back PASS with no
 *   warnings.
 *
 * When ZATCA publishes a new rule, add or adjust a scenario here first.
 */
class Scenarios
{
    /**
     * @return array<string, Closure(): InvoiceData>
     */
    public static function all(string $date, string $time): array
    {
        $earlier = date('Y-m-d', strtotime($date . ' -3 days'));

        $standard = fn(string $number) => self::document($number, $date, $time)->standard()->setBuyer(self::buyer());
        $simplified = fn(string $number) => self::document($number, $date, $time)->simplified();

        return [
            // --- Standard tax invoices and notes (B2B, cleared) ---------------
            'standard invoice' => fn() => self::lines($standard('S-001'), self::line(1, 2, 50), self::line(2, 1, 199.99)),
            'standard credit note' => fn() => self::lines(
                $standard('S-002')->creditNote()
                    ->addBillingReference(['id' => 'S-001', 'issue_date' => $earlier])
                    ->addPaymentMeans(['code' => '10', 'instruction_note' => 'Goods returned']),
                self::line(1, 1, 50)
            ),
            'standard debit note' => fn() => self::lines(
                $standard('S-003')->debitNote()
                    ->addBillingReference(['id' => 'S-001'])
                    ->addPaymentMeans(['code' => '10', 'instruction_note' => 'Price adjustment']),
                self::line(1, 1, 10)
            ),
            'document allowance, amount' => fn() => self::lines($standard('S-004'), self::line(1, 10, 100))
                ->addAllowance(['amount' => 50, 'reason' => 'Loyalty discount', 'reason_code' => '95']),
            'document allowance, percentage' => fn() => self::lines($standard('S-005'), self::line(1, 10, 100))
                ->addAllowance(['base_amount' => 1000, 'multiplier' => 10, 'reason' => 'Volume discount']),
            'document charge, amount' => fn() => self::lines($standard('S-006'), self::line(1, 10, 100))
                ->addCharge(['amount' => 25, 'reason' => 'Freight service', 'reason_code' => 'FC']),
            'document charge, percentage' => fn() => self::lines($standard('S-007'), self::line(1, 10, 100))
                ->addCharge(['base_amount' => 1000, 'multiplier' => 2.5, 'reason' => 'Packing', 'reason_code' => 'ABL']),
            'several document allowances and charges' => fn() => self::lines($standard('S-008'), self::line(1, 10, 100))
                ->addAllowance(['amount' => 20, 'reason' => 'Coupon'])
                ->addAllowance(['base_amount' => 1000, 'multiplier' => 5, 'reason' => 'Early payment'])
                ->addCharge(['amount' => 15, 'reason' => 'Freight service', 'reason_code' => 'FC']),
            'line allowance and line charge' => fn() => self::lines(
                $standard('S-009'),
                self::line(1, 4, 25)->setAllowanceAmount(5)->setAllowanceReason('Promo')->setAllowanceReasonCode('95'),
                self::line(2, 1, 300)->setChargeAmount(12.5)->setChargeReason('Cleaning')->setChargeReasonCode('CG')
            ),
            'several line allowances, with percentage' => fn() => self::lines(
                $standard('S-010'),
                self::line(1, 10, 100)
                    ->addAllowance(['base_amount' => 1000, 'multiplier' => 10, 'reason' => 'Volume discount'])
                    ->addAllowance(['amount' => 50, 'reason' => 'Seasonal'])
                    ->addCharge(['base_amount' => 1000, 'multiplier' => 1, 'reason' => 'Handling', 'reason_code' => 'HD'])
            ),
            'price discount and base quantity' => fn() => self::lines(
                $standard('S-011'),
                self::line(1, 24, 120)->setPriceDiscount(12)->setBaseQuantity(12),
                self::line(2, 3, 19.995)->setPriceDiscount(0.495)
            ),
            'export, zero rated' => fn() => self::lines(
                $standard('S-012')->export()->setBuyer(self::foreignBuyer()),
                self::line(1, 5, 1000, 'Z', 'VATEX-SA-32')
            ),
            'exempt, financial services' => fn() => self::lines($standard('S-013'), self::line(1, 1, 5000, 'E', 'VATEX-SA-29')),
            'exempt, real estate' => fn() => self::lines($standard('S-014'), self::line(1, 1, 250000, 'E', 'VATEX-SA-30')),
            'out of scope' => fn() => self::lines(
                $standard('S-015'),
                self::line(1, 1, 750, 'O', 'VATEX-SA-OOS', 'Supply outside the scope of KSA VAT')
            ),
            'mixed VAT categories' => fn() => self::lines(
                $standard('S-016'),
                self::line(1, 2, 100),
                self::line(2, 1, 400, 'Z', 'VATEX-SA-35'),
                self::line(3, 1, 90, 'E', 'VATEX-SA-29-7'),
                self::line(4, 3, 10, 'O', 'VATEX-SA-OOS', 'Not subject to VAT')
            ),
            'mixed categories with allowances per category' => fn() => self::lines(
                $standard('S-017'),
                self::line(1, 2, 100),
                self::line(2, 1, 400, 'Z', 'VATEX-SA-35')
            )
                ->addAllowance(['amount' => 20, 'reason' => 'discount', 'tax_category' => 'S', 'tax_percent' => 15])
                ->addAllowance(['amount' => 40, 'reason' => 'discount', 'tax_category' => 'Z']),
            'standard rate of 5 percent' => fn() => self::lines($standard('S-018'), self::line(1, 1, 100)->setTaxPercent(5), self::line(2, 1, 100)),
            'zero rated, duty free' => fn() => self::lines($standard('S-019'), self::line(1, 1, 100, 'Z', 'VATEX-SA-DUTYFREE')),
            'zero rated, royal decree' => fn() => self::lines($standard('S-020'), self::line(1, 1, 100, 'Z', 'VATEX-SA-ROYALDECREE')),
            'zero rated, customs suspension' => fn() => self::lines($standard('S-021'), self::line(1, 1, 100, 'Z', 'VATEX-SA-32(bis)')),
            'private education with national id' => fn() => self::lines(
                $standard('S-022')->setBuyer(self::buyerWithId('NAT', '1058529940')),
                self::line(1, 1, 12000, 'Z', 'VATEX-SA-EDU')
            ),
            'third party' => fn() => self::lines($standard('S-023')->thirdParty(), self::line(1, 1, 100)),
            'nominal supply' => fn() => self::lines($standard('S-024')->nominal(), self::line(1, 1, 100)),
            'summary' => fn() => self::lines($standard('S-025')->summary()->setSupplyDate($earlier)->setSupplyEndDate($date), self::line(1, 30, 12.5)),
            'self billed' => fn() => self::lines(
                $standard('S-026')->selfBilled()->setNote('Self-billed invoice issued by the buyer', 'en'),
                self::line(1, 1, 100)
            ),
            'prepayment invoice' => fn() => self::lines($standard('S-027')->prepaymentInvoice(), self::line(1, 1, 1000)),
            'final invoice deducting a prepayment' => fn() => self::lines($standard('S-028'), self::line(1, 1, 5000))
                ->addPrepayment(['id' => 'S-027', 'issue_date' => $earlier, 'issue_time' => '09:00:00', 'taxable_amount' => 1000]),
            'final invoice deducting two prepayments' => fn() => self::lines(
                $standard('S-029'),
                self::line(1, 1, 5000),
                self::line(2, 1, 100, 'Z', 'VATEX-SA-35')
            )
                ->addPrepayment(['id' => 'S-027', 'issue_date' => $earlier, 'issue_time' => '09:00:00', 'taxable_amount' => 1000])
                ->addPrepayment([
                    'id' => 'S-027Z', 'issue_date' => $earlier, 'issue_time' => '09:05:00', 'taxable_amount' => 50,
                    'tax_category' => 'Z', 'tax_exemption_reason_code' => 'VATEX-SA-35',
                ]),
            'foreign currency' => fn() => self::lines(
                $standard('S-030')->setDocumentCurrencyCode('USD')->setTaxExchangeRate(3.75),
                self::line(1, 3, 99.99)
            ),
            'payable rounding' => fn() => self::lines($standard('S-031')->setPayableRoundingAmount(-0.02), self::line(1, 1, 100.02)),
            'buyer identified by commercial registration' => fn() => self::lines(
                $standard('S-032')->setBuyer(self::buyerWithId('CRN', '2050012345')),
                self::line(1, 1, 100)
            ),
            'GCC buyer' => fn() => self::lines(
                $standard('S-033')->setBuyer(
                    self::foreignBuyer()->setCountryCode('AE')->setCityName('Dubai')
                        ->setPartyIdentification('784190275031604')->setPartyIdentificationId('GCC')
                ),
                self::line(1, 1, 100)
            ),
            'credit transfer with account and terms' => fn() => self::lines(
                $standard('S-034')->setDueDate($date)->addPaymentMeans([
                    'code' => '30', 'due_date' => $date, 'payment_id' => 'REF-778',
                    'account_id' => 'SA0380000000608010167519', 'payment_terms' => 'Payment within 30 days',
                ]),
                self::line(1, 1, 100)
            ),
            'several payment means' => fn() => self::lines(
                $standard('S-035')->addPaymentMeans(['code' => '48'])->addPaymentMeans(['id' => '2', 'code' => '42']),
                self::line(1, 1, 100)
            ),
            'note and document references' => fn() => self::lines(
                $standard('S-036')->setNote('Thank you for your business', 'en')
                    ->addDocumentReference(['id' => 'DN-2026-0042', 'type' => 'Delivery note'])
                    ->addDocumentReference(['id' => 'GRN-17']),
                self::line(1, 1, 100)
            ),
            'purchase order, contract and tax point date' => fn() => self::lines(
                $standard('S-037')->setPurchaseOrderReference('PO-2026-0042')->setContractReference('CONTRACT-9')->setTaxPointDate($date),
                self::line(1, 1, 100)
            ),
            'buyer contact and legal form' => fn() => self::lines(
                $standard('S-038')->setBuyer(
                    self::buyer()->setCompanyLegalForm('Limited Liability Company')
                        ->setContact('Ahmed Ali', '+966500000000', 'ap@example.com;finance@example.com', 'Accounts payable')
                ),
                self::line(1, 1, 100)
            ),
            'rounding stress' => fn() => self::lines(
                $standard('S-039'),
                self::line(1, 3, 3.335),
                self::line(2, 7, 0.145),
                self::line(3, 1.5, 33.333),
                self::line(4, 0.333, 99.99),
                self::line(5, 1000, 0.015)
            ),
            'Arabic content' => fn() => self::lines(
                $standard('S-040')
                    ->setSeller(self::seller()->setRegistrationName('شركة السرعة القصوى للتقنية المحدودة')
                        ->setStreetName('الأمير سلطان')->setCitySubdivisionName('المربع')->setCityName('الرياض'))
                    ->setBuyer(self::buyer()->setRegistrationName('شركة نماذج فاتورة المحدودة')),
                self::line(1, 2, 50)->setItemName('كتاب تعليمي')->setDescription('وصف الصنف')
            ),
            'delivery location' => fn() => self::lines(
                $standard('S-041')->setDeliveryInfo(['street' => 'King Fahd Rd', 'city' => 'Jeddah', 'postal_code' => '21442', 'country_code' => 'SA']),
                self::line(1, 1, 100)
            ),
            'item identifiers and unit code' => fn() => self::lines(
                $standard('S-042'),
                self::line(1, 1, 100)->setItemCode('SKU-1')->setBuyersItemCode('B-9')->setStandardItemCode('06281100003456', '0160')->setUnitCode('KGM')
            ),
            'credit note with allowance' => fn() => self::lines(
                $standard('S-043')->creditNote()->addBillingReference(['id' => 'S-004'])
                    ->addPaymentMeans(['code' => '10', 'instruction_note' => 'Partial return']),
                self::line(1, 2, 100)
            )->addAllowance(['amount' => 10, 'reason' => 'discount']),
            'zero value line' => fn() => self::lines($standard('S-044'), self::line(1, 1, 100), self::line(2, 1, 0)->setItemName('Free sample')),
            'supply date in the past' => fn() => self::lines($standard('S-045')->setSupplyDate($earlier), self::line(1, 1, 100)),

            // --- Simplified tax invoices and notes (B2C, reported) -----------
            'simplified invoice' => fn() => self::lines($simplified('P-001'), self::line(1, 2, 50)),
            'simplified credit note' => fn() => self::lines(
                $simplified('P-002')->creditNote()->addBillingReference(['id' => 'P-001'])
                    ->addPaymentMeans(['code' => '10', 'instruction_note' => 'Refund']),
                self::line(1, 1, 50)
            ),
            'simplified debit note' => fn() => self::lines(
                $simplified('P-003')->debitNote()->addBillingReference(['id' => 'P-001'])
                    ->addPaymentMeans(['code' => '10', 'instruction_note' => 'Additional charge']),
                self::line(1, 1, 5)
            ),
            'simplified with buyer name' => fn() => self::lines(
                $simplified('P-004')->setBuyer((new BuyerData())->setRegistrationName('Walk-in Customer')),
                self::line(1, 1, 80)
            ),
            'simplified with buyer VAT number' => fn() => self::lines(
                $simplified('P-005')->setBuyer((new BuyerData())->setRegistrationName('Small Shop')->setVatNumber('399999999800003')),
                self::line(1, 1, 80)
            ),
            'simplified summary' => fn() => self::lines(
                $simplified('P-006')->summary()->setSupplyDate($earlier)->setSupplyEndDate($date)
                    ->setBuyer((new BuyerData())->setRegistrationName('Monthly Subscriber')),
                self::line(1, 30, 3)
            ),
            'simplified third party' => fn() => self::lines($simplified('P-007')->thirdParty(), self::line(1, 1, 20)),
            'simplified nominal' => fn() => self::lines($simplified('P-008')->nominal(), self::line(1, 1, 20)),
            'simplified export' => fn() => self::lines($simplified('P-009')->export(), self::line(1, 1, 100, 'Z', 'VATEX-SA-32')),
            'simplified private healthcare with national id' => fn() => self::lines(
                $simplified('P-010')->setBuyer(
                    (new BuyerData())->setRegistrationName('Saudi Citizen')->setPartyIdentification('1058529940')->setPartyIdentificationId('NAT')
                ),
                self::line(1, 1, 300, 'Z', 'VATEX-SA-HEA')
            ),
            'simplified zero rated' => fn() => self::lines($simplified('P-011'), self::line(1, 1, 60, 'Z', 'VATEX-SA-35')),
            'simplified with discounts' => fn() => self::lines($simplified('P-012'), self::line(1, 3, 40)->setAllowanceAmount(6))
                ->addAllowance(['amount' => 4, 'reason' => 'Coupon']),
            'simplified with charge' => fn() => self::lines($simplified('P-013'), self::line(1, 1, 40))
                ->addCharge(['amount' => 5, 'reason' => 'Delivery', 'reason_code' => 'DL']),
            'simplified deducting a prepayment' => fn() => self::lines($simplified('P-014'), self::line(1, 1, 500))
                ->addPrepayment(['id' => 'P-PRE-1', 'issue_date' => $earlier, 'issue_time' => '08:00:00', 'taxable_amount' => 100]),
            'simplified cash rounding' => fn() => self::lines($simplified('P-015')->setPayableRoundingAmount(0.03), self::line(1, 1, 86.93)),
            'simplified mixed categories' => fn() => self::lines(
                $simplified('P-016'),
                self::line(1, 1, 10),
                self::line(2, 1, 20, 'Z', 'VATEX-SA-35'),
                self::line(3, 1, 30, 'E', 'VATEX-SA-29')
            ),
            'simplified with forty lines' => fn() => self::lines(
                $simplified('P-017'),
                ...array_map(fn(int $i) => self::line($i, $i, 1.11 * $i), range(1, 40))
            ),
            'simplified in foreign currency' => fn() => self::lines(
                $simplified('P-018')->setDocumentCurrencyCode('EUR')->setTaxExchangeRate(4.05),
                self::line(1, 2, 19.99)
            ),
        ];
    }

    /**
     * Scenarios as a PHPUnit data provider: [name => [name]].
     *
     * @return array<string, array{0: string}>
     */
    public static function names(): array
    {
        $names = array_keys(self::all('2024-01-15', '10:30:00'));

        return array_combine($names, array_map(fn(string $name) => [$name], $names));
    }

    public static function seller(): SellerData
    {
        return (new SellerData())
            ->setRegistrationName('Maximum Speed Tech Supply LTD')->setVatNumber('399999999900003')
            ->setPartyIdentification('1010010000')->setPartyIdentificationId('CRN')
            ->setStreetName('Prince Sultan')->setBuildingNumber('2322')->setCitySubdivisionName('Al-Murabba')
            ->setCityName('Riyadh')->setPostalZone('23333')->setCountryCode('SA');
    }

    public static function buyer(): BuyerData
    {
        return (new BuyerData())
            ->setRegistrationName('Fatoora Samples LTD')->setVatNumber('399999999800003')
            ->setStreetName('Salah Al-Din')->setBuildingNumber('1111')->setCitySubdivisionName('Al-Murooj')
            ->setCityName('Riyadh')->setPostalZone('12222')->setCountryCode('SA');
    }

    /** A Saudi buyer without a VAT number, identified by another ID (BR-KSA-81). */
    public static function buyerWithId(string $scheme, string $id): BuyerData
    {
        return self::buyer()->setVatNumber('')->setPartyIdentification($id)->setPartyIdentificationId($scheme);
    }

    public static function foreignBuyer(): BuyerData
    {
        return (new BuyerData())
            ->setRegistrationName('Acme Trading GmbH')->setPartyIdentification('DE811907980')->setPartyIdentificationId('OTH')
            ->setStreetName('Hauptstrasse 5')->setCityName('Berlin')->setCountryCode('DE');
    }

    public static function line(int $id, float $quantity, float $price, string $category = 'S', ?string $reasonCode = null, ?string $reasonText = null): InvoiceLineData
    {
        $line = (new InvoiceLineData())->setId($id)->setItemName("Item $id")->setQuantity($quantity)->setUnitPrice($price)->setTaxPercent(15);

        if ($category !== 'S') {
            $line->setTaxCategory($category, $reasonCode, $reasonText);
        }

        return $line;
    }

    private static function document(string $number, string $date, string $time): InvoiceData
    {
        return (new InvoiceData())->setInvoiceNumber($number)->setIssueDate($date)->setIssueTime($time)
            ->setSeller(self::seller())->taxInvoice();
    }

    private static function lines(InvoiceData $invoice, InvoiceLineData ...$lines): InvoiceData
    {
        foreach ($lines as $line) {
            $invoice->addLine($line->calculateTotals());
        }

        return $invoice;
    }
}

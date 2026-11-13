<?php

namespace KhaledHajSalem\Zatca\Tests;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;
use KhaledHajSalem\Zatca\Support\Certificate;
use KhaledHajSalem\Zatca\Support\InvoiceSigner;
use KhaledHajSalem\Zatca\Support\TaxCategory;
use KhaledHajSalem\Zatca\Tests\Fixtures\Scenarios;
use KhaledHajSalem\Zatca\Validation\InvoiceValidator;
use KhaledHajSalem\Zatca\ZatcaInvoice;
use PHPUnit\Framework\TestCase;

/**
 * Rules and fields ZATCA introduced after XML Implementation Standard v1.2
 * (SDK releases 3.2.9 to 3.4.8). Severities and thresholds were established
 * against the developer-portal validator.
 */
class CurrentRulesTest extends TestCase
{
    private const DATE = '2024-01-15';

    private function invoice(bool $simplified = false): InvoiceData
    {
        $invoice = (new InvoiceData())->setInvoiceNumber('INV-1')->setIssueDate(self::DATE)->setIssueTime('10:30:00')
            ->setSeller(Scenarios::seller())->taxInvoice();

        return $simplified ? $invoice->simplified() : $invoice->standard()->setBuyer(Scenarios::buyer());
    }

    private function withLine(InvoiceData $invoice, ?InvoiceLineData $line = null): InvoiceData
    {
        return $invoice->addLine(($line ?? Scenarios::line(1, 1, 100))->calculateTotals());
    }

    /**
     * @return array<int, array{severity: string, rule: string, field: string, message: string}>
     */
    private function issues(InvoiceData $invoice, string $now = self::DATE . ' 18:00:00'): array
    {
        $invoice->calculateTotals();

        return (new InvoiceValidator(new DateTimeImmutable($now, new DateTimeZone('Asia/Riyadh'))))->validate($invoice);
    }

    private function severityOf(string $rule, array $issues): ?string
    {
        foreach ($issues as $issue) {
            if ($issue['rule'] === $rule) {
                return $issue['severity'];
            }
        }

        return null;
    }

    private function xpath(InvoiceData $invoice): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML((new ZatcaInvoice())->generateXml($invoice, '8e6000cf-1a98-4174-b3e7-b5d5954bc10d'));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        return $xpath;
    }

    // --- BR-KSA-84: standard rate is 5 or 15 --------------------------------

    public function testStandardRateOtherThanFiveOrFifteenIsAnError(): void
    {
        $issues = $this->issues($this->withLine($this->invoice(), Scenarios::line(1, 1, 100)->setTaxPercent(10)));

        $this->assertSame('error', $this->severityOf('BR-KSA-84', $issues));
    }

    /**
     * @dataProvider standardRates
     */
    public function testFiveAndFifteenPercentAreAccepted(float $rate): void
    {
        $issues = $this->issues($this->withLine($this->invoice(), Scenarios::line(1, 1, 100)->setTaxPercent($rate)));

        $this->assertSame([], $issues);
    }

    public function standardRates(): array
    {
        return ['5%' => [5.0], '15%' => [15.0]];
    }

    public function testStandardRateRuleCoversAllowancesChargesAndPrepayments(): void
    {
        $invoice = $this->withLine($this->invoice())
            ->addAllowance(['amount' => 1, 'tax_category' => 'S', 'tax_percent' => 10])
            ->addCharge(['amount' => 1, 'reason' => 'Freight', 'reason_code' => 'FC', 'tax_category' => 'S', 'tax_percent' => 7.5])
            ->addPrepayment(['id' => 'P-1', 'issue_date' => self::DATE, 'issue_time' => '09:00:00', 'taxable_amount' => 10, 'tax_percent' => 20]);

        $fields = array_column(array_filter($this->issues($invoice), fn(array $i) => $i['rule'] === 'BR-KSA-84'), 'field');

        $this->assertEqualsCanonicalizing(['allowances.0.tax_percent', 'charges.0.tax_percent', 'prepayments.0.tax_percent'], array_values($fields));
    }

    // --- BR-KSA-05 and BR-CL-KSA-14 ----------------------------------------

    public function testUnknownInvoiceTypeCodeIsAnError(): void
    {
        $issues = $this->issues($this->withLine($this->invoice()->setInvoiceTypeCode('380')));

        $this->assertSame('error', $this->severityOf('BR-KSA-05', $issues));
    }

    public function testSignerRefusesAQrCodeZatcaWouldReject(): void
    {
        // A 700-character seller name pushes the QR code past 1000 characters.
        $invoice = $this->withLine($this->invoice()->setSeller(Scenarios::seller()->setRegistrationName(str_repeat('Long name ', 70))));
        $xml = (new ZatcaInvoice())->generateXml($invoice, '8e6000cf-1a98-4174-b3e7-b5d5954bc10d');

        $this->expectException(ZatcaException::class);
        $this->expectExceptionMessage('BR-CL-KSA-14');

        InvoiceSigner::signInvoice($xml, $this->certificate());
    }

    // --- BR-KSA-31 amended: simplified export -------------------------------

    public function testSimplifiedExportIsValidAndGenerated(): void
    {
        $invoice = $this->withLine($this->invoice(true)->export(), Scenarios::line(1, 1, 100, 'Z', 'VATEX-SA-32'));

        $this->assertSame([], $this->issues($invoice));
        $this->assertSame('0200100', $this->xpath($invoice)->evaluate('string(/*/cbc:InvoiceTypeCode/@name)'));
    }

    // --- New exemption reason codes -----------------------------------------

    /**
     * @dataProvider newZeroRatedCodes
     */
    public function testNewZeroRatedReasonCodes(string $code, string $text): void
    {
        $invoice = $this->withLine($this->invoice(), Scenarios::line(1, 1, 100, 'Z', $code));
        $xpath = $this->xpath($invoice);

        $this->assertSame($code, $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode)'));
        $this->assertSame($text, $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReason)'));
        $this->assertArrayHasKey($code, TaxCategory::reasons('Z'));
    }

    public function newZeroRatedCodes(): array
    {
        return [
            'duty free' => ['VATEX-SA-DUTYFREE', 'Qualified Supply of Goods in Duty Free area'],
            'royal decree' => ['VATEX-SA-ROYALDECREE', 'Supply on which the Government bears the VAT'],
            'customs suspension' => ['VATEX-SA-32(bis)', 'Supply under Customs Suspension Arrangement'],
        ];
    }

    public function testNewCodesAreNotAcceptedForOtherCategories(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Scenarios::line(1, 1, 100, 'E', 'VATEX-SA-DUTYFREE');
    }

    // --- Out of scope carries a 0.00 rate (avoids BR-CO-17) -----------------

    public function testOutOfScopeHasZeroRateEverywhere(): void
    {
        $xpath = $this->xpath($this->withLine($this->invoice(), Scenarios::line(1, 1, 100, 'O', 'VATEX-SA-OOS', 'Outside scope')));

        $this->assertSame('0.00', $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:Percent)'));
        $this->assertSame('0.00', $xpath->evaluate('string(/*/cac:InvoiceLine/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent)'));
    }

    // --- BR-KSA-93: prepayment exemption reason (KSA-41 / KSA-42) -----------

    public function testZeroRatedPrepaymentWithoutReasonCodeWarns(): void
    {
        $invoice = $this->withLine($this->invoice(), Scenarios::line(1, 1, 500, 'Z', 'VATEX-SA-35'))
            ->addPrepayment(['id' => 'P-1', 'issue_date' => self::DATE, 'issue_time' => '09:00:00', 'taxable_amount' => 50, 'tax_category' => 'Z']);

        $this->assertSame('warning', $this->severityOf('BR-KSA-93', $this->issues($invoice)));
    }

    public function testPrepaymentReasonIsWrittenToTheLineBreakdownOnly(): void
    {
        $invoice = $this->withLine($this->invoice(), Scenarios::line(1, 1, 500, 'Z', 'VATEX-SA-35'))
            ->addPrepayment([
                'id' => 'P-1', 'issue_date' => self::DATE, 'issue_time' => '09:00:00', 'taxable_amount' => 50,
                'tax_category' => 'Z', 'tax_exemption_reason_code' => 'VATEX-SA-35',
            ]);

        $this->assertNull($this->severityOf('BR-KSA-93', $this->issues($invoice)));

        $xpath = $this->xpath($invoice);
        $line = '/*/cac:InvoiceLine[cac:DocumentReference]';
        $this->assertSame('VATEX-SA-35', $xpath->evaluate("string($line/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode)"));
        $this->assertSame('Medicines and medical equipment', $xpath->evaluate("string($line/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReason)"));
        $this->assertSame(0.0, $xpath->evaluate("count($line/cac:Item/cac:ClassifiedTaxCategory/cbc:TaxExemptionReasonCode)"));
    }

    public function testPrepaymentReasonMustBelongToItsCategory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->invoice()->addPrepayment([
            'id' => 'P-1', 'issue_date' => self::DATE, 'issue_time' => '09:00:00', 'taxable_amount' => 50,
            'tax_category' => 'Z', 'tax_exemption_reason_code' => 'VATEX-SA-29',
        ]);
    }

    // --- BR-KSA-F-07 to F-13: other seller / buyer identifiers --------------

    /**
     * @dataProvider identifiers
     */
    public function testBuyerIdentifierFormats(string $scheme, string $id, ?string $expectedRule): void
    {
        $issues = $this->issues($this->withLine($this->invoice()->setBuyer(Scenarios::buyerWithId($scheme, $id))));
        $rules = array_column($issues, 'rule');

        if ($expectedRule === null) {
            $this->assertSame([], $issues);

            return;
        }

        $this->assertSame([$expectedRule], $rules);
        $this->assertSame('warning', $issues[0]['severity'], 'ZATCA accepts the document and warns');
    }

    public function identifiers(): array
    {
        return [
            'TIN valid' => ['TIN', '3105509872', null],
            'TIN must start with 3' => ['TIN', '1105509872', 'BR-KSA-F-07'],
            'CRN valid' => ['CRN', '2050012345', null],
            'CRN nine digits' => ['CRN', '205001234', 'BR-KSA-F-08'],
            'CRN eleven digits' => ['CRN', '20500987651', 'BR-KSA-F-08'],
            'CRN with a letter' => ['CRN', '20500A8765', 'BR-KSA-F-08'],
            '700 valid' => ['700', '7001469825', null],
            '700 must start with 7' => ['700', '1001469825', 'BR-KSA-F-09'],
            'NAT valid' => ['NAT', '1058529940', null],
            'NAT must start with 1' => ['NAT', '2058529940', 'BR-KSA-F-10'],
            'IQA valid' => ['IQA', '2475382946', null],
            'IQA must start with 2' => ['IQA', '1475382946', 'BR-KSA-F-11'],
            'MOM has no format rule' => ['MOM', '4030291877', null],
            'MLS has no format rule' => ['MLS', '4030291877', null],
            'SAG has no format rule' => ['SAG', '4030291877', null],
            'PAS free format' => ['PAS', 'P4366918', null],
            'OTH free format' => ['OTH', 'ABC9988', null],
            'GCC valid' => ['GCC', '784190275031604', null],
            'seven digits counting up are fine' => ['CRN', '9012345690', null],
            'eight digits counting up' => ['CRN', '9012345679', 'BR-KSA-F-13'],
            'seven digits counting down are fine' => ['CRN', '1987654319', null],
            'eight digits counting down' => ['CRN', '1987654329', 'BR-KSA-F-13'],
            'seven repeated digits are fine' => ['CRN', '2055555559', null],
            'eight repeated digits' => ['CRN', '2055555555', 'BR-KSA-F-13'],
            'placeholder in a free-format scheme' => ['OTH', '1111111111', 'BR-KSA-F-13'],
            'placeholder passport' => ['PAS', '12345678', 'BR-KSA-F-13'],
        ];
    }

    public function testSellerIdentifierIsCheckedToo(): void
    {
        $invoice = $this->withLine($this->invoice()->setSeller(Scenarios::seller()->setPartyIdentification('1234567890')));

        $issues = $this->issues($invoice);

        $this->assertSame(['BR-KSA-F-13'], array_column($issues, 'rule'));
        $this->assertSame('seller.party_identification', $issues[0]['field']);
    }

    // --- BR-KSA-F-06: character limits --------------------------------------

    /**
     * @dataProvider lengthLimits
     */
    public function testCharacterLimits(callable $mutate, string $field): void
    {
        $invoice = $this->withLine($this->invoice());
        $mutate($invoice);

        $matching = array_values(array_filter($this->issues($invoice), fn(array $i) => $i['field'] === $field));

        $this->assertCount(1, $matching, "Expected one issue on $field");
        $this->assertSame('BR-KSA-F-06', $matching[0]['rule']);
        $this->assertSame('warning', $matching[0]['severity']);
    }

    public function lengthLimits(): array
    {
        $text = fn(int $length) => str_repeat('a', $length);

        return [
            'invoice number over 127' => [fn(InvoiceData $i) => $i->setInvoiceNumber($text(128)), 'invoice_number'],
            'note over 1000' => [fn(InvoiceData $i) => $i->setNote($text(1001)), 'note'],
            'purchase order over 127' => [fn(InvoiceData $i) => $i->setPurchaseOrderReference($text(128)), 'purchase_order_reference'],
            'contract over 127' => [fn(InvoiceData $i) => $i->setContractReference($text(128)), 'contract_reference'],
            'seller name over 1000' => [fn(InvoiceData $i) => $i->getSeller()->setRegistrationName($text(1001)), 'seller.registration_name'],
            'seller city over 127' => [fn(InvoiceData $i) => $i->getSeller()->setCityName($text(128)), 'seller.city_name'],
            'seller district over 127' => [fn(InvoiceData $i) => $i->getSeller()->setCitySubdivisionName($text(128)), 'seller.city_subdivision_name'],
            'buyer name over 1000' => [fn(InvoiceData $i) => $i->getBuyer()->setRegistrationName($text(1001)), 'buyer.registration_name'],
            'buyer additional street over 127' => [fn(InvoiceData $i) => $i->getBuyer()->setAdditionalStreetName($text(128)), 'buyer.additional_street_name'],
            'payment account over 127' => [fn(InvoiceData $i) => $i->addPaymentMeans(['code' => '30', 'account_id' => $text(128)]), 'payment_means.0.account_id'],
            'item name under 3' => [fn(InvoiceData $i) => $i->getLines()[0]->setItemName('AB'), 'lines.0.item_name'],
            'item name over 1000' => [fn(InvoiceData $i) => $i->getLines()[0]->setItemName($text(1001)), 'lines.0.item_name'],
            'item code over 127' => [fn(InvoiceData $i) => $i->getLines()[0]->setItemCode($text(128)), 'lines.0.item_code'],
        ];
    }

    public function testLimitsCountCharactersNotBytes(): void
    {
        // 500 Arabic characters are 1000 bytes: inside the 1000-character limit.
        $invoice = $this->withLine($this->invoice());
        $invoice->getBuyer()->setRegistrationName(str_repeat('ش', 500));
        $invoice->getLines()[0]->setItemName('كتب');

        $this->assertSame([], $this->issues($invoice));
    }

    // --- BR-KSA-97: foreign currency ----------------------------------------

    public function testExchangeRateOfOneWarns(): void
    {
        $invoice = $this->withLine($this->invoice()->setDocumentCurrencyCode('USD')->setTaxExchangeRate(1.0));

        $this->assertSame('warning', $this->severityOf('BR-KSA-97', $this->issues($invoice)));
    }

    public function testExchangeRateIsWrittenAfterAllowancesAndBeforeTaxTotals(): void
    {
        $invoice = $this->withLine($this->invoice()->setDocumentCurrencyCode('USD')->setTaxExchangeRate(3.75))
            ->addCharge(['amount' => 5, 'reason' => 'Freight', 'reason_code' => 'FC']);
        $xpath = $this->xpath($invoice);

        $this->assertSame('USD', $xpath->evaluate('string(/*/cac:TaxExchangeRate/cbc:SourceCurrencyCode)'));
        $this->assertSame('SAR', $xpath->evaluate('string(/*/cac:TaxExchangeRate/cbc:TargetCurrencyCode)'));
        $this->assertSame('3.75', $xpath->evaluate('string(/*/cac:TaxExchangeRate/cbc:CalculationRate)'));
        $this->assertSame('cac:AllowanceCharge', $xpath->evaluate('name(/*/cac:TaxExchangeRate/preceding-sibling::*[1])'));
        $this->assertSame('cac:TaxTotal', $xpath->evaluate('name(/*/cac:TaxExchangeRate/following-sibling::*[1])'));
        $this->assertNull($this->severityOf('BR-KSA-97', $this->issues($invoice)));
    }

    public function testNoExchangeRateBlockForSarInvoices(): void
    {
        $this->assertSame(0.0, $this->xpath($this->withLine($this->invoice()))->evaluate('count(/*/cac:TaxExchangeRate)'));
    }

    // --- BR-KSA-98: 24-hour reporting window --------------------------------

    public function testSimplifiedInvoiceOlderThanADayWarns(): void
    {
        $invoice = $this->withLine($this->invoice(true));

        $this->assertNull($this->severityOf('BR-KSA-98', $this->issues($invoice, '2024-01-16 10:00:00')));
        $this->assertSame('warning', $this->severityOf('BR-KSA-98', $this->issues($invoice, '2024-01-16 11:00:00')));
    }

    public function testLateReportingWindowHonoursUtcIssueTimes(): void
    {
        // 10:30:00Z is 13:30 in Riyadh, so 24 hours end at 13:30 the next day.
        $invoice = $this->withLine($this->invoice(true)->setIssueTime('10:30:00Z'));

        $this->assertNull($this->severityOf('BR-KSA-98', $this->issues($invoice, '2024-01-16 13:00:00')));
        $this->assertSame('warning', $this->severityOf('BR-KSA-98', $this->issues($invoice, '2024-01-16 14:00:00')));
    }

    public function testStandardInvoicesHaveNoReportingWindow(): void
    {
        $this->assertNull($this->severityOf('BR-KSA-98', $this->issues($this->withLine($this->invoice()), '2024-03-01 10:00:00')));
    }

    // --- Optional fields ------------------------------------------------------

    public function testOrderContractAndTaxPointAreWrittenInSchemaOrder(): void
    {
        $invoice = $this->withLine(
            $this->invoice()->creditNote()->setNote('Note')
                ->setPurchaseOrderReference('PO-1')->setContractReference('CT-1')->setTaxPointDate(self::DATE)
                ->addBillingReference(['id' => 'INV-0', 'issue_date' => '2024-01-10'])
                ->addPaymentMeans(['code' => '10', 'instruction_note' => 'Return'])
        );
        $xpath = $this->xpath($invoice);

        $this->assertSame('PO-1', $xpath->evaluate('string(/*/cac:OrderReference/cbc:ID)'));
        $this->assertSame('CT-1', $xpath->evaluate('string(/*/cac:ContractDocumentReference/cbc:ID)'));
        $this->assertSame(self::DATE, $xpath->evaluate('string(/*/cbc:TaxPointDate)'));
        $this->assertSame('2024-01-10', $xpath->evaluate('string(/*/cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate)'));

        // UBL order: Note, TaxPointDate, DocumentCurrencyCode ... OrderReference,
        // BillingReference, ContractDocumentReference, AdditionalDocumentReference.
        $this->assertSame('cbc:Note', $xpath->evaluate('name(/*/cbc:TaxPointDate/preceding-sibling::*[1])'));
        $this->assertSame('cbc:DocumentCurrencyCode', $xpath->evaluate('name(/*/cbc:TaxPointDate/following-sibling::*[1])'));
        $this->assertSame('cac:BillingReference', $xpath->evaluate('name(/*/cac:OrderReference/following-sibling::*[1])'));
        $this->assertSame('cac:BillingReference', $xpath->evaluate('name(/*/cac:ContractDocumentReference/preceding-sibling::*[1])'));
        $this->assertSame('cac:AdditionalDocumentReference', $xpath->evaluate('name(/*/cac:ContractDocumentReference/following-sibling::*[1])'));
    }

    public function testInvalidTaxPointAndBillingDatesAreErrors(): void
    {
        $invoice = $this->withLine($this->invoice()->setTaxPointDate('15/01/2024')->addBillingReference(['id' => 'X', 'issue_date' => '2024-13-01']));

        $fields = array_column(array_filter($this->issues($invoice), fn(array $i) => $i['rule'] === 'BR-KSA-F-01'), 'field');

        $this->assertEqualsCanonicalizing(['tax_point_date', 'billing_references.0.issue_date'], $fields);
    }

    public function testBuyerContactAndLegalForm(): void
    {
        $buyer = Scenarios::buyer()->setCompanyLegalForm('Limited Liability Company')
            ->setContact('Ahmed Ali', '+966500000000', 'ap@example.com;finance@example.com', 'Accounts payable');
        $xpath = $this->xpath($this->withLine($this->invoice()->setBuyer($buyer)));
        $party = '/*/cac:AccountingCustomerParty/cac:Party';

        $this->assertSame('Limited Liability Company', $xpath->evaluate("string($party/cac:PartyLegalEntity/cbc:CompanyLegalForm)"));
        $this->assertSame('Ahmed Ali', $xpath->evaluate("string($party/cac:Contact/cbc:Name)"));
        $this->assertSame('+966500000000', $xpath->evaluate("string($party/cac:Contact/cbc:Telephone)"));
        $this->assertSame('ap@example.com;finance@example.com', $xpath->evaluate("string($party/cac:Contact/cbc:ElectronicMail)"));
        $this->assertSame('Accounts payable', $xpath->evaluate("string($party/cac:Contact/cbc:Note)"));
        $this->assertSame('cac:PartyLegalEntity', $xpath->evaluate("name($party/cac:Contact/preceding-sibling::*[1])"));
    }

    public function testNoContactElementWithoutContactDetails(): void
    {
        $xpath = $this->xpath($this->withLine($this->invoice()->setBuyer(Scenarios::buyer()->setContact())));

        $this->assertSame(0.0, $xpath->evaluate('count(//cac:Contact)'));
        $this->assertSame(0.0, $xpath->evaluate('count(//cbc:CompanyLegalForm)'));
    }

    public function testPaymentAccountAndTerms(): void
    {
        $invoice = $this->withLine($this->invoice()->addPaymentMeans([
            'code' => '30', 'payment_id' => 'REF-1', 'account_id' => 'SA0380000000608010167519', 'payment_terms' => 'Net 30',
        ]));
        $xpath = $this->xpath($invoice);

        $this->assertSame('SA0380000000608010167519', $xpath->evaluate('string(/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID)'));
        $this->assertSame('Net 30', $xpath->evaluate('string(/*/cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:PaymentNote)'));
        $this->assertSame('cbc:PaymentID', $xpath->evaluate('name(/*/cac:PaymentMeans/cac:PayeeFinancialAccount/preceding-sibling::*[1])'));
    }

    // --- Several allowances and charges on one line -------------------------

    public function testSeveralLineAllowancesAndChargesWithPercentages(): void
    {
        $line = Scenarios::line(1, 10, 100)
            ->setAllowanceAmount(20)->setAllowanceReason('Promo')
            ->addAllowance(['base_amount' => 1000, 'multiplier' => 10, 'reason' => 'Volume'])
            ->addCharge(['base_amount' => 1000, 'multiplier' => 1, 'reason' => 'Handling', 'reason_code' => 'HD']);
        $invoice = $this->withLine($this->invoice(), $line);

        $this->assertSame(120.0, $line->getTotalAllowanceAmount());
        $this->assertSame(10.0, $line->getTotalChargeAmount());
        // 1000 - 120 + 10
        $this->assertSame(890.0, $line->getTaxExclusiveAmount());
        $this->assertSame(133.5, $line->getTaxAmount());

        $xpath = $this->xpath($invoice);
        $ac = '/*/cac:InvoiceLine/cac:AllowanceCharge';

        $this->assertSame(3.0, $xpath->evaluate("count($ac)"));
        $this->assertSame('20.00', $xpath->evaluate("string({$ac}[1]/cbc:Amount)"));
        $this->assertSame('10', $xpath->evaluate("string({$ac}[2]/cbc:MultiplierFactorNumeric)"));
        $this->assertSame('100.00', $xpath->evaluate("string({$ac}[2]/cbc:Amount)"));
        $this->assertSame('1000.00', $xpath->evaluate("string({$ac}[2]/cbc:BaseAmount)"));
        $this->assertSame('true', $xpath->evaluate("string({$ac}[3]/cbc:ChargeIndicator)"));
        $this->assertSame('HD', $xpath->evaluate("string({$ac}[3]/cbc:AllowanceChargeReasonCode)"));
        $this->assertSame('890.00', $xpath->evaluate('string(/*/cac:InvoiceLine/cbc:LineExtensionAmount)'));
        $this->assertSame([], $this->issues($invoice));
    }

    public function testLineChargeAddedWithoutReasonThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Scenarios::line(1, 1, 100)->addCharge(['amount' => 5]);
    }

    public function testLineAllowancePercentageMustMatchAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Scenarios::line(1, 1, 100)->addAllowance(['base_amount' => 100, 'multiplier' => 10, 'amount' => 11]);
    }

    // --- Hash stability --------------------------------------------------------

    private function certificate(): Certificate
    {
        $storage = __DIR__ . '/../storage';

        return new Certificate(
            (string)file_get_contents("$storage/certificate.pem"),
            (string)file_get_contents("$storage/private.pem"),
            trim((string)file_get_contents("$storage/secret.txt"))
        );
    }

    public function testCustomDocumentReferencesComeBeforeTheQrReference(): void
    {
        $invoice = $this->withLine($this->invoice()->addDocumentReference(['id' => 'DN-1', 'type' => 'Delivery note']));
        $xpath = $this->xpath($invoice);

        $ids = [];
        foreach ($xpath->query('/*/cac:AdditionalDocumentReference/cbc:ID') as $id) {
            $ids[] = $id->nodeValue;
        }

        $this->assertSame(['ICV', 'PIH', 'DN-1', 'QR'], $ids);
        $this->assertSame('cac:Signature', $xpath->evaluate('name(/*/cac:AdditionalDocumentReference[cbc:ID="QR"]/following-sibling::*[1])'));
    }

    public function testSignerRefusesALayoutThatWouldChangeTheHash(): void
    {
        // Reproduce the 1.1.0 layout: a document reference between the QR
        // reference and cac:Signature. ZATCA rejected such documents with
        // "invalid-invoice-hash"; the signer must now stop them.
        $xml = (new ZatcaInvoice())->generateXml(
            $this->withLine($this->invoice()->addDocumentReference(['id' => 'DN-1'])),
            '8e6000cf-1a98-4174-b3e7-b5d5954bc10d'
        );

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $custom = $xpath->query('/*/cac:AdditionalDocumentReference[cbc:ID="DN-1"]')->item(0);
        $signature = $xpath->query('/*/cac:Signature')->item(0);
        $signature->parentNode->insertBefore($custom, $signature);

        $this->expectException(ZatcaException::class);
        $this->expectExceptionMessage('does not hash to the value that was signed');

        InvoiceSigner::signInvoice($dom->saveXML(), $this->certificate());
    }

    public function testComputingADigestDoesNotAlterTheDocument(): void
    {
        $signed = InvoiceSigner::signInvoice(
            (new ZatcaInvoice())->generateXml($this->withLine($this->invoice()), '8e6000cf-1a98-4174-b3e7-b5d5954bc10d'),
            $this->certificate()
        );

        $wrapper = \KhaledHajSalem\Zatca\Support\InvoiceExtension::fromString($signed->getXML());
        $before = $wrapper->toXml();
        $wrapper->computeXmlDigest();

        $this->assertSame($before, $wrapper->toXml());
        $this->assertStringContainsString('ds:SignatureValue', $wrapper->toXml());
    }
}

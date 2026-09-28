<?php

namespace KhaledHajSalem\Zatca\Tests;

use DateTimeImmutable;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Exceptions\ZatcaValidationException;
use KhaledHajSalem\Zatca\Validation\InvoiceValidator;
use PHPUnit\Framework\TestCase;

class InvoiceValidatorTest extends TestCase
{
    private function seller(): SellerData
    {
        return (new SellerData())
            ->setRegistrationName('Seller Co')
            ->setVatNumber('399999999900003')
            ->setPartyIdentification('1010010000')
            ->setPartyIdentificationId('CRN')
            ->setStreetName('King Fahd Road')
            ->setBuildingNumber('1234')
            ->setCitySubdivisionName('Olaya')
            ->setCityName('Riyadh')
            ->setPostalZone('12345')
            ->setCountryCode('SA');
    }

    private function buyer(): BuyerData
    {
        return (new BuyerData())
            ->setRegistrationName('Buyer Co')
            ->setVatNumber('311111111111113')
            ->setStreetName('Tahlia Street')
            ->setBuildingNumber('5678')
            ->setCitySubdivisionName('Rawdah')
            ->setCityName('Jeddah')
            ->setPostalZone('23456')
            ->setCountryCode('SA');
    }

    private function line(): InvoiceLineData
    {
        return (new InvoiceLineData())->setId(1)->setItemName('Item')->setQuantity(1)->setUnitPrice(100)
            ->setTaxPercent(15)->calculateTotals();
    }

    /**
     * A complete, correct standard tax invoice.
     */
    private function validInvoice(): InvoiceData
    {
        $invoice = (new InvoiceData())->standard()->taxInvoice()
            ->setInvoiceNumber('INV-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setSeller($this->seller())
            ->setBuyer($this->buyer());
        $invoice->addLine($this->line());

        return $invoice;
    }

    private function issues(InvoiceData $invoice): array
    {
        $invoice->calculateTotals();

        return (new InvoiceValidator(new DateTimeImmutable('2024-06-01')))->validate($invoice);
    }

    private function rules(InvoiceData $invoice): array
    {
        return array_column($this->issues($invoice), 'rule');
    }

    public function testValidInvoiceHasNoIssues()
    {
        $this->assertSame([], $this->issues($this->validInvoice()));
    }

    public function testValidSimplifiedInvoiceWithoutBuyerHasNoIssues()
    {
        $invoice = $this->validInvoice()->simplified();
        $invoice->setBuyer(new BuyerData());

        $this->assertSame([], $this->issues($invoice));
    }

    public function testIssueFormat()
    {
        $invoice = $this->validInvoice();
        $invoice->getSeller()->setVatNumber('123');

        $this->assertSame([[
            'severity' => 'error',
            'rule'     => 'BR-KSA-40',
            'field'    => 'seller.vat_number',
            'message'  => 'Seller VAT number must be 15 digits, starting and ending with 3.',
        ]], $this->issues($invoice));
    }

    public function testHeaderRules()
    {
        $invoice = $this->validInvoice()
            ->setInvoiceNumber('')
            ->setIssueDate('2024-07-01')            // after "now"
            ->setIssueTime('10:30')
            ->setDueDate('15/01/2024')
            ->setTaxCurrencyCode('USD')
            ->setInvoiceCounter('A1')
            ->setPreviousInvoiceHash('not-a-hash');

        $rules = $this->rules($invoice);

        foreach (['BR-02', 'BR-KSA-04', 'BR-KSA-70', 'BR-KSA-F-01', 'BR-KSA-EN16931-02', 'BR-KSA-34', 'BR-KSA-26'] as $rule) {
            $this->assertContains($rule, $rules);
        }
    }

    public function testRealPreviousHashIsAccepted()
    {
        $invoice = $this->validInvoice()->setPreviousInvoiceHash(base64_encode(hash('sha256', 'x', true)));

        $this->assertNotContains('BR-KSA-26', $this->rules($invoice));
    }

    public function testSellerRules()
    {
        $invoice = $this->validInvoice();
        $invoice->getSeller()
            ->setPartyIdentificationId('XYZ')
            ->setCitySubdivisionName('')
            ->setBuildingNumber('12')
            ->setPostalZone('123')
            ->setCountryCode('Saudi');

        $rules = $this->rules($invoice);

        foreach (['BR-KSA-08', 'BR-KSA-09', 'BR-KSA-37', 'BR-KSA-66', 'BR-CL-14'] as $rule) {
            $this->assertContains($rule, $rules);
        }
    }

    public function testMissingSeller()
    {
        $invoice = (new InvoiceData())->standard()->setInvoiceNumber('1')->setIssueDate('2024-01-15')->setIssueTime('10:00:00');
        $invoice->setBuyer($this->buyer());
        $invoice->addLine($this->line());

        $this->assertContains('BR-06', $this->rules($invoice));
    }

    public function testStandardBuyerRules()
    {
        $invoice = $this->validInvoice();
        $invoice->setBuyer((new BuyerData())->setCountryCode('SA')->setPartyIdentification('12-34')->setPartyIdentificationId('ABC'));

        $rules = $this->rules($invoice);

        foreach (['BR-KSA-42', 'BR-KSA-14', 'BR-KSA-10', 'BR-KSA-63'] as $rule) {
            $this->assertContains($rule, $rules);
        }
    }

    public function testStandardBuyerWithoutVatNeedsId()
    {
        $invoice = $this->validInvoice();
        $invoice->getBuyer()->setVatNumber('');

        $this->assertContains('BR-KSA-81', $this->rules($invoice));

        $invoice->getBuyer()->setPartyIdentification('1010101010')->setPartyIdentificationId('CRN');
        $this->assertNotContains('BR-KSA-81', $this->rules($invoice));
    }

    public function testBuyerVatAndPostalFormat()
    {
        $invoice = $this->validInvoice();
        $invoice->getBuyer()->setVatNumber('12345')->setPostalZone('ABC');

        $rules = $this->rules($invoice);

        $this->assertContains('BR-KSA-44', $rules);
        $this->assertContains('BR-KSA-67', $rules);
    }

    public function testForeignBuyerNeedsNoSaudiAddressFields()
    {
        $invoice = $this->validInvoice();
        $invoice->getBuyer()->setCountryCode('AE')->setBuildingNumber('')->setPostalZone('')->setCitySubdivisionName('');

        $this->assertSame([], $this->issues($invoice));
    }

    public function testCreditNoteRules()
    {
        $invoice = $this->validInvoice()->creditNote();

        $issues = $this->issues($invoice);
        $rules = array_column($issues, 'rule');

        $this->assertContains('BR-KSA-56', $rules);
        $warning = array_values(array_filter($issues, fn($i) => $i['rule'] === 'BR-KSA-17'));
        $this->assertSame('warning', $warning[0]['severity']);

        $invoice->addBillingReference(['id' => 'INV-000'])->addPaymentMeans(['code' => '10', 'instruction_note' => 'Returned goods']);
        $this->assertSame([], $this->issues($invoice));
    }

    public function testPaymentMeansCode()
    {
        $invoice = $this->validInvoice()->addPaymentMeans(['code' => '99']);

        $this->assertContains('BR-KSA-16', $this->rules($invoice));
    }

    public function testLineRules()
    {
        $invoice = $this->validInvoice();
        $invoice->addLine((new InvoiceLineData())->setId(2)->setQuantity(-1)->setUnitPrice(10)->setTaxPercent(0)->calculateTotals());

        $rules = $this->rules($invoice);

        foreach (['BR-25', 'BR-KSA-F-04', 'BR-KSA-DEC-02'] as $rule) {
            $this->assertContains($rule, $rules);
        }
    }

    public function testNoLines()
    {
        $invoice = (new InvoiceData())->standard()->setInvoiceNumber('1')->setIssueDate('2024-01-15')->setIssueTime('10:00:00')
            ->setSeller($this->seller())->setBuyer($this->buyer());

        $this->assertContains('BR-16', $this->rules($invoice));
    }

    public function testEducationExemptionNeedsNationalId()
    {
        $invoice = $this->validInvoice()->simplified();
        $invoice->setBuyer(new BuyerData());
        $invoice->addLine((new InvoiceLineData())->setId(2)->setItemName('Tuition')->setQuantity(1)->setUnitPrice(1000)
            ->setTaxCategory('Z', 'VATEX-SA-EDU')->calculateTotals());

        $rules = $this->rules($invoice);
        $this->assertContains('BR-KSA-49', $rules);
        $this->assertContains('BR-KSA-25', $rules);

        $invoice->setBuyer((new BuyerData())->setRegistrationName('Student')->setPartyIdentification('1000000000')->setPartyIdentificationId('NAT'));
        $this->assertSame([], $this->issues($invoice));
    }

    public function testStandardRatedAllowanceNeedsRate()
    {
        $invoice = $this->validInvoice()->addAllowance(['amount' => 5, 'tax_category' => 'S', 'tax_percent' => 0]);

        $this->assertContains('BR-S-06', $this->rules($invoice));
    }

    public function testTransactionRules()
    {
        $invoice = $this->validInvoice()->export()->selfBilled()->setSupplyDate('2024-01-10')->setSupplyEndDate('2024-01-05');

        $rules = $this->rules($invoice);

        foreach (['BR-KSA-07', 'BR-KSA-46', 'BR-KSA-36'] as $rule) {
            $this->assertContains($rule, $rules);
        }
    }

    public function testSimplifiedSummaryRules()
    {
        $invoice = $this->validInvoice()->simplified()->summary()->export();
        $invoice->setBuyer(new BuyerData());

        $rules = $this->rules($invoice);

        foreach (['BR-KSA-31', 'BR-KSA-71', 'BR-KSA-72'] as $rule) {
            $this->assertContains($rule, $rules);
        }
    }

    public function testNegativePayableAmount()
    {
        $invoice = $this->validInvoice()->addPrepayment([
            'id' => 'PP-1', 'issue_date' => '2024-01-01', 'issue_time' => '9:00', 'taxable_amount' => 500,
        ]);

        $rules = $this->rules($invoice);
        $this->assertContains('BR-KSA-F-04', $rules);
        $this->assertContains('BR-KSA-F-05', $rules);
    }

    public function testErrorsFilter()
    {
        $issues = [
            ['severity' => 'error', 'rule' => 'A', 'field' => 'x', 'message' => ''],
            ['severity' => 'warning', 'rule' => 'B', 'field' => 'y', 'message' => ''],
        ];

        $this->assertSame(['A'], array_column(InvoiceValidator::errors($issues), 'rule'));
    }

    public function testInvoiceDataValidateShortcut()
    {
        $invoice = $this->validInvoice()->setIssueDate('2020-01-01');

        $this->assertSame([], $invoice->validate());
    }

    public function testValidationException()
    {
        $issues = [['severity' => 'error', 'rule' => 'BR-KSA-40', 'field' => 'seller.vat_number', 'message' => 'Bad VAT.']];
        $exception = new ZatcaValidationException($issues);

        $this->assertSame($issues, $exception->getIssues());
        $this->assertStringContainsString('[BR-KSA-40] seller.vat_number: Bad VAT.', $exception->getMessage());
    }
}

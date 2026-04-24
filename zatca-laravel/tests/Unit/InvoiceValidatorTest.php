<?php

namespace KhaledHajSalem\ZatcaLaravel\Tests\Unit;

use KhaledHajSalem\ZatcaLaravel\Support\InvoiceValidator;
use KhaledHajSalem\ZatcaPHP\Data\BuyerData;
use KhaledHajSalem\ZatcaPHP\Data\InvoiceData;
use KhaledHajSalem\ZatcaPHP\Data\InvoiceLineData;
use KhaledHajSalem\ZatcaPHP\Data\SellerData;
use PHPUnit\Framework\TestCase;

class InvoiceValidatorTest extends TestCase
{
    private InvoiceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new InvoiceValidator();
    }

    public function test_validates_missing_invoice_number(): void
    {
        $invoice = new InvoiceData();
        $invoice->setIssueDate('2025-01-15');
        $invoice->setIssueTime('10:00:00');
        $invoice->setUUID('test-uuid');
        $invoice->simplified();
        $invoice->taxInvoice();

        $seller = $this->validSeller();
        $lines = [$this->validLine()];

        $errors = $this->validator->validate($invoice, $seller, null, $lines);

        $this->assertContains('Invoice number is required', $errors);
    }

    public function test_validates_invalid_date_format(): void
    {
        $invoice = $this->validInvoice();
        $invoice->setIssueDate('15-01-2025');

        $seller = $this->validSeller();
        $lines = [$this->validLine()];

        $errors = $this->validator->validate($invoice, $seller, null, $lines);

        $this->assertContains('Issue date must be in Y-m-d format', $errors);
    }

    public function test_validates_seller_vat(): void
    {
        $invoice = $this->validInvoice();
        $seller = new SellerData();
        $seller->setRegistrationName('Test');
        $seller->setTaxRegistrationNumber('12345');

        $lines = [$this->validLine()];

        $errors = $this->validator->validate($invoice, $seller, null, $lines);

        $this->assertContains('Seller VAT number must be exactly 15 digits, starting and ending with 3', $errors);
    }

    public function test_validates_valid_seller_vat(): void
    {
        $invoice = $this->validInvoice();
        $seller = $this->validSeller();
        $lines = [$this->validLine()];

        $errors = $this->validator->validate($invoice, $seller, null, $lines);

        $this->assertEmpty($errors);
    }

    public function test_validates_empty_lines(): void
    {
        $invoice = $this->validInvoice();
        $seller = $this->validSeller();

        $errors = $this->validator->validate($invoice, $seller, null, []);

        $this->assertContains('At least one line item is required', $errors);
    }

    public function test_validates_buyer_for_standard(): void
    {
        $invoice = $this->validInvoice();
        $invoice->standard();
        $invoice->taxInvoice();

        $seller = $this->validSeller();
        $lines = [$this->validLine()];

        $errors = $this->validator->validate($invoice, $seller, null, $lines);

        $this->assertContains('Buyer information is required for standard (B2B) invoices', $errors);
    }

    public function test_passes_with_valid_data(): void
    {
        $invoice = $this->validInvoice();
        $seller = $this->validSeller();
        $lines = [$this->validLine()];

        $errors = $this->validator->validate($invoice, $seller, null, $lines);

        $this->assertEmpty($errors);
    }

    private function validInvoice(): InvoiceData
    {
        $invoice = new InvoiceData();
        $invoice->setInvoiceNumber('INV-001');
        $invoice->setIssueDate('2025-01-15');
        $invoice->setIssueTime('10:00:00');
        $invoice->setUUID('test-uuid-123');
        $invoice->simplified();
        $invoice->taxInvoice();

        return $invoice;
    }

    private function validSeller(): SellerData
    {
        $seller = new SellerData();
        $seller->setRegistrationName('Test Company');
        $seller->setTaxRegistrationNumber('399999999900003');

        return $seller;
    }

    private function validLine(): InvoiceLineData
    {
        $line = new InvoiceLineData();
        $line->setIndex(1)
            ->setItemName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(100)
            ->setTaxPercent(15)
            ->calculateTotals();

        return $line;
    }
}

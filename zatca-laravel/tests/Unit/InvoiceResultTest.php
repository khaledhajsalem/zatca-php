<?php

namespace KhaledHajSalem\ZatcaLaravel\Tests\Unit;

use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;
use KhaledHajSalem\ZatcaLaravel\Support\InvoiceResult;
use PHPUnit\Framework\TestCase;

class InvoiceResultTest extends TestCase
{
    public function test_successful_result(): void
    {
        $invoice = new ZatcaInvoice();
        $invoice->uuid = 'test-uuid';
        $invoice->invoice_hash = 'abc123';
        $invoice->qr_code = 'qr-base64';
        $invoice->signed_xml = '<xml/>';
        $invoice->status = 'cleared';
        $invoice->zatca_warnings = [];
        $invoice->zatca_errors = [];

        $result = new InvoiceResult($invoice, ['test' => true]);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('test-uuid', $result->uuid);
        $this->assertEquals('abc123', $result->hash);
        $this->assertEquals('cleared', $result->status);
        $this->assertEmpty($result->warnings());
        $this->assertEmpty($result->errors());
    }

    public function test_failed_result(): void
    {
        $invoice = new ZatcaInvoice();
        $invoice->uuid = 'test-uuid';
        $invoice->invoice_hash = null;
        $invoice->qr_code = null;
        $invoice->signed_xml = null;
        $invoice->status = 'rejected';
        $invoice->zatca_warnings = [];
        $invoice->zatca_errors = ['Invalid VAT'];

        $result = new InvoiceResult($invoice, []);

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('rejected', $result->status);
        $this->assertNotEmpty($result->errors());
    }

    public function test_to_array(): void
    {
        $invoice = new ZatcaInvoice();
        $invoice->uuid = 'test-uuid';
        $invoice->invoice_hash = 'hash';
        $invoice->qr_code = null;
        $invoice->signed_xml = null;
        $invoice->status = 'reported';
        $invoice->zatca_warnings = ['W1'];
        $invoice->zatca_errors = [];

        $result = new InvoiceResult($invoice, []);
        $array = $result->toArray();

        $this->assertEquals('test-uuid', $array['uuid']);
        $this->assertEquals('hash', $array['hash']);
        $this->assertTrue($array['success']);
        $this->assertEquals(['W1'], $array['warnings']);
    }
}

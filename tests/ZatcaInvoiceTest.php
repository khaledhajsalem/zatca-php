<?php

namespace KhaledHajSalem\Zatca\Tests;

use PHPUnit\Framework\TestCase;
use KhaledHajSalem\Zatca\ZatcaInvoice;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;

class ZatcaInvoiceTest extends TestCase
{
    public function testGenerateXml()
    {
        // Create invoice data
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('INV-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setCurrencyCode('SAR')
            ->setInvoiceTypeCode('388')
            ->setDocumentCurrencyCode('SAR');

        // Set seller information
        $seller = new SellerData();
        $seller->setRegistrationName('Test Company')
            ->setVatNumber('123456789012345')
            ->setAddress('123 Test Street, Riyadh, Saudi Arabia')
            ->setCountryCode('SA');

        $invoiceData->setSeller($seller);

        // Set buyer information
        $buyer = new BuyerData();
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setAddress('456 Customer Street, Jeddah, Saudi Arabia')
            ->setCountryCode('SA');

        $invoiceData->setBuyer($buyer);

        // Add invoice line
        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();

        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        // Generate XML
        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);

        // Assertions
        $this->assertIsString($xml);
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('Invoice', $xml);
        $this->assertStringContainsString('INV-001', $xml);
        $this->assertStringContainsString('Test Company', $xml);
        $this->assertStringContainsString('Customer Company', $xml);
    }

    public function testInvoiceDataSetters()
    {
        $invoiceData = new InvoiceData();
        
        $invoiceData->setInvoiceNumber('TEST-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setCurrencyCode('SAR');

        $this->assertEquals('TEST-001', $invoiceData->getInvoiceNumber());
        $this->assertEquals('2024-01-15', $invoiceData->getIssueDate());
        $this->assertEquals('10:30:00', $invoiceData->getIssueTime());
        $this->assertEquals('SAR', $invoiceData->getCurrencyCode());
    }

    public function testSellerDataSetters()
    {
        $seller = new SellerData();
        
        $seller->setRegistrationName('Test Company')
            ->setVatNumber('123456789012345')
            ->setCountryCode('SA');

        $this->assertEquals('Test Company', $seller->getRegistrationName());
        $this->assertEquals('123456789012345', $seller->getVatNumber());
        $this->assertEquals('SA', $seller->getCountryCode());
    }

    public function testBuyerDataSetters()
    {
        $buyer = new BuyerData();
        
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setCountryCode('SA');

        $this->assertEquals('Customer Company', $buyer->getRegistrationName());
        $this->assertEquals('987654321098765', $buyer->getVatNumber());
        $this->assertEquals('SA', $buyer->getCountryCode());
    }

    public function testInvoiceLineDataSetters()
    {
        $line = new InvoiceLineData();
        
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(2)
            ->setUnitPrice(50.00)
            ->setTaxPercent(15.0);

        $this->assertEquals(1, $line->getId());
        $this->assertEquals('Test Product', $line->getItemName());
        $this->assertEquals(2.0, $line->getQuantity());
        $this->assertEquals(50.00, $line->getUnitPrice());
        $this->assertEquals(15.0, $line->getTaxPercent());
    }

    public function testInvoiceLineCalculation()
    {
        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(2)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();

        $this->assertEquals(200.00, $line->getLineExtensionAmount()); // 2 * 100
        $this->assertEquals(30.00, $line->getTaxAmount()); // 200 * 0.15
        $this->assertEquals(230.00, $line->getTaxInclusiveAmount()); // 200 + 30
    }

    public function testInvoiceCalculation()
    {
        $invoiceData = new InvoiceData();
        
        // Add line 1
        $line1 = new InvoiceLineData();
        $line1->setId(1)
            ->setItemName('Product 1')
            ->setQuantity(2)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();

        // Add line 2
        $line2 = new InvoiceLineData();
        $line2->setId(2)
            ->setItemName('Product 2')
            ->setQuantity(1)
            ->setUnitPrice(50.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();

        $invoiceData->addLine($line1);
        $invoiceData->addLine($line2);
        $invoiceData->calculateTotals();

        $this->assertEquals(250.00, $invoiceData->getTaxExclusiveAmount()); // 200 + 50
        $this->assertEquals(37.50, $invoiceData->getTaxTotalAmount()); // 30 + 7.5
        $this->assertEquals(287.50, $invoiceData->getTaxInclusiveAmount()); // 250 + 37.5
        $this->assertEquals(287.50, $invoiceData->getPayableAmount());
    }
} 
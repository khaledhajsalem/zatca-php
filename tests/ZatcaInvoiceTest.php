<?php

namespace KhaledHajSalem\Zatca\Tests;

use PHPUnit\Framework\TestCase;
use KhaledHajSalem\Zatca\ZatcaInvoice;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use DOMDocument;
use DOMXPath;

class ZatcaInvoiceTest extends TestCase
{
    /**
     * Build a DOMXPath over the given XML with the cbc/cac namespaces registered.
     */
    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        return $xpath;
    }

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

    /**
     * Test that InstructionNote is NOT included for Standard Tax Invoice (388)
     * per ZATCA requirement BR-KSA-17
     */
    public function testInstructionNoteNotIncludedForStandardInvoice()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('INV-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setCurrencyCode('SAR')
            ->setInvoiceTypeCode('388') // Standard Tax Invoice
            ->setDocumentCurrencyCode('SAR');

        $seller = new SellerData();
        $seller->setRegistrationName('Test Company')
            ->setVatNumber('123456789012345')
            ->setCountryCode('SA');
        $invoiceData->setSeller($seller);

        $buyer = new BuyerData();
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setCountryCode('SA');
        $invoiceData->setBuyer($buyer);

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);

        // InstructionNote should NOT be present for standard invoice (388)
        $this->assertStringNotContainsString('إلغاء أو تعليق التوريدات', $xml);
    }

    /**
     * Test that InstructionNote IS included for Debit Note (383)
     * per ZATCA requirement BR-KSA-17
     */
    public function testInstructionNoteIncludedForDebitNote()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('DN-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setCurrencyCode('SAR')
            ->setInvoiceTypeCode('383') // Debit Note
            ->setDocumentCurrencyCode('SAR');

        $seller = new SellerData();
        $seller->setRegistrationName('Test Company')
            ->setVatNumber('123456789012345')
            ->setCountryCode('SA');
        $invoiceData->setSeller($seller);

        $buyer = new BuyerData();
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setCountryCode('SA');
        $invoiceData->setBuyer($buyer);

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Additional Charge')
            ->setQuantity(1)
            ->setUnitPrice(25.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);

        // InstructionNote SHOULD be present for debit note (383)
        $this->assertStringContainsString('إلغاء أو تعليق التوريدات', $xml);
    }

    /**
     * Test that InstructionNote IS included for Credit Note (381)
     * per ZATCA requirement BR-KSA-17
     */
    public function testInstructionNoteIncludedForCreditNote()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('CN-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setCurrencyCode('SAR')
            ->setInvoiceTypeCode('381') // Credit Note
            ->setDocumentCurrencyCode('SAR');

        $seller = new SellerData();
        $seller->setRegistrationName('Test Company')
            ->setVatNumber('123456789012345')
            ->setCountryCode('SA');
        $invoiceData->setSeller($seller);

        $buyer = new BuyerData();
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setCountryCode('SA');
        $invoiceData->setBuyer($buyer);

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Return')
            ->setQuantity(1)
            ->setUnitPrice(50.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);

        // InstructionNote SHOULD be present for credit note (381)
        $this->assertStringContainsString('إلغاء أو تعليق التوريدات', $xml);
    }

    /**
     * Test that InstructionNote is NOT included for Prepayment Invoice (386)
     * per ZATCA requirement BR-KSA-17
     */
    public function testInstructionNoteNotIncludedForPrepaymentInvoice()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('PP-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setCurrencyCode('SAR')
            ->setInvoiceTypeCode('386') // Prepayment Invoice
            ->setDocumentCurrencyCode('SAR');

        $seller = new SellerData();
        $seller->setRegistrationName('Test Company')
            ->setVatNumber('123456789012345')
            ->setCountryCode('SA');
        $invoiceData->setSeller($seller);

        $buyer = new BuyerData();
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setCountryCode('SA');
        $invoiceData->setBuyer($buyer);

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Advance Payment')
            ->setQuantity(1)
            ->setUnitPrice(500.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);

        // InstructionNote should NOT be present for prepayment invoice (386)
        $this->assertStringNotContainsString('إلغاء أو تعليق التوريدات', $xml);
    }

    /**
     * Line-level discount (BG-27) must be expressed as a line AllowanceCharge,
     * the line LineExtensionAmount must be NET, and document totals must not
     * double-subtract the line allowance.
     */
    public function testLineLevelAllowance()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('INV-DISC-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(40.00)
            ->setAllowanceAmount(4.00)
            ->setAllowanceReason('Loyalty discount')
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);
        $xpath = $this->xpath($xml);

        // Line LineExtensionAmount is NET (40 - 4).
        $this->assertEquals('36.00', $xpath->evaluate('string(//cac:InvoiceLine/cbc:LineExtensionAmount)'));

        // Line-level AllowanceCharge present with ChargeIndicator=false and Amount=4.00.
        $this->assertEquals(1, $xpath->evaluate('count(//cac:InvoiceLine/cac:AllowanceCharge)'));
        $this->assertEquals('false', $xpath->evaluate('string(//cac:InvoiceLine/cac:AllowanceCharge/cbc:ChargeIndicator)'));
        $this->assertEquals('Loyalty discount', $xpath->evaluate('string(//cac:InvoiceLine/cac:AllowanceCharge/cbc:AllowanceChargeReason)'));
        $this->assertEquals('4.00', $xpath->evaluate('string(//cac:InvoiceLine/cac:AllowanceCharge/cbc:Amount)'));

        // The discount must no longer be duplicated inside cac:Price.
        $this->assertEquals(0, $xpath->evaluate('count(//cac:InvoiceLine/cac:Price/cac:AllowanceCharge)'));
        $this->assertEquals('40.00', $xpath->evaluate('string(//cac:InvoiceLine/cac:Price/cbc:PriceAmount)'));

        // Line tax on the NET amount: 36 * 0.15 = 5.40.
        $this->assertEquals('5.40', $xpath->evaluate('string(//cac:InvoiceLine/cac:TaxTotal/cbc:TaxAmount)'));

        // Document totals.
        $this->assertEquals('36.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount)'));
        $this->assertEquals('41.40', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount)'));
        $this->assertEquals('41.40', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)'));
    }

    /**
     * A line with no allowance must not emit a line-level AllowanceCharge, and
     * LineExtensionAmount must equal the gross amount.
     */
    public function testLineWithoutAllowanceHasNoLineAllowanceCharge()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->setInvoiceNumber('INV-NODISC-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(2)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);
        $xpath = $this->xpath($xml);

        // No line-level AllowanceCharge.
        $this->assertEquals(0, $xpath->evaluate('count(//cac:InvoiceLine/cac:AllowanceCharge)'));

        // LineExtensionAmount equals gross (2 * 100).
        $this->assertEquals('200.00', $xpath->evaluate('string(//cac:InvoiceLine/cbc:LineExtensionAmount)'));

        // Document totals unchanged from prior behaviour.
        $this->assertEquals('200.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount)'));
        $this->assertEquals('230.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount)'));
        $this->assertEquals('230.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)'));
    }

    /**
     * Simplified (B2C) invoice with a walk-in buyer (no VAT/ID/address) emits a
     * minimal AccountingCustomerParty (country only), never an empty <cbc:ID>.
     */
    public function testSimplifiedWalkInBuyerHasMinimalCustomerParty()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->simplified()
            ->setInvoiceNumber('INV-B2C-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $buyer = new BuyerData(); // no VAT, no identification, no address, no name
        $invoiceData->setBuyer($buyer);

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);
        $xpath = $this->xpath($xml);

        // UBL requires the customer party; an unidentified walk-in gets only
        // the country, so no element is empty.
        $this->assertEquals(1, $xpath->evaluate('count(//cac:AccountingCustomerParty)'));
        $this->assertEquals('SA', $xpath->evaluate('string(//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode)'));
        $this->assertEquals(1, $xpath->evaluate('count(//cac:AccountingCustomerParty//*[not(*)])'));

        // And certainly no empty buyer identification.
        $this->assertEquals(0, $xpath->evaluate('count(//cac:AccountingCustomerParty//cbc:ID)'));
    }

    public function testSimplifiedWithoutBuyerStillHasCustomerParty()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->simplified()->setInvoiceNumber('INV-B2C-002')->setIssueDate('2024-01-15')->setIssueTime('10:30:00');
        $invoiceData->addLine($this->categoryLine(1, 100.00));

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        // AccountingCustomerParty must come right after AccountingSupplierParty's position (before Delivery).
        $this->assertEquals('AccountingCustomerParty', $xpath->evaluate('local-name(//cac:Delivery/preceding-sibling::*[1])'));
        $this->assertEquals('SA', $xpath->evaluate('string(//cac:AccountingCustomerParty//cbc:IdentificationCode)'));
    }

    /**
     * Standard (B2B) invoice with a fully populated buyer must still emit the
     * full AccountingCustomerParty structure.
     */
    public function testStandardBuyerEmitsFullParty()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->standard()
            ->setInvoiceNumber('INV-B2B-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $buyer = new BuyerData();
        $buyer->setRegistrationName('Customer Company')
            ->setVatNumber('987654321098765')
            ->setPartyIdentification('987654321098765')
            ->setPartyIdentificationId('TIN')
            ->setStreetName('Customer Street')
            ->setBuildingNumber('1234')
            ->setCityName('Jeddah')
            ->setPostalZone('23456')
            ->setCountryCode('SA');
        $invoiceData->setBuyer($buyer);

        $line = new InvoiceLineData();
        $line->setId(1)
            ->setItemName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(100.00)
            ->setTaxPercent(15.0)
            ->calculateTotals();
        $invoiceData->addLine($line);
        $invoiceData->calculateTotals();

        $zatcaInvoice = new ZatcaInvoice();
        $xml = $zatcaInvoice->generateXml($invoiceData);
        $xpath = $this->xpath($xml);

        $base = '//cac:AccountingCustomerParty/cac:Party';
        $this->assertEquals(1, $xpath->evaluate("count($base/cac:PartyIdentification)"));
        $this->assertEquals('987654321098765', $xpath->evaluate("string($base/cac:PartyIdentification/cbc:ID)"));
        $this->assertEquals(1, $xpath->evaluate("count($base/cac:PostalAddress)"));
        $this->assertEquals('987654321098765', $xpath->evaluate("string($base/cac:PartyTaxScheme/cbc:CompanyID)"));
        $this->assertEquals('Customer Company', $xpath->evaluate("string($base/cac:PartyLegalEntity/cbc:RegistrationName)"));
    }

    /**
     * Build a line with the given VAT category.
     */
    private function categoryLine(int $id, float $price, string $category = 'S', ?string $reasonCode = null, ?string $reason = null): InvoiceLineData
    {
        $line = new InvoiceLineData();
        $line->setId($id)
            ->setItemName("Item $id")
            ->setQuantity(1)
            ->setUnitPrice($price)
            ->setTaxPercent(15.0)
            ->setTaxCategory($category, $reasonCode, $reason)
            ->calculateTotals();
        return $line;
    }

    public function testNonStandardCategoriesForceZeroTax()
    {
        foreach (['Z' => 'VATEX-SA-32', 'E' => 'VATEX-SA-29', 'O' => 'VATEX-SA-OOS'] as $category => $code) {
            $line = $this->categoryLine(1, 100.00, $category, $code, 'Reason');

            $this->assertEquals($category, $line->getTaxCategory());
            $this->assertEquals(0.0, $line->getTaxPercent());
            $this->assertEquals(0.0, $line->getTaxAmount());
            $this->assertEquals(100.0, $line->getTaxInclusiveAmount());
        }
    }

    public function testExemptionReasonTextDefaultsFromCode()
    {
        $line = $this->categoryLine(1, 100.00, 'Z', 'VATEX-SA-32');

        $this->assertEquals('VATEX-SA-32', $line->getTaxExemptionReasonCode());
        $this->assertEquals('Export of goods', $line->getTaxExemptionReason());
    }

    public function testInvalidTaxCategoryThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceLineData())->setTaxCategory('X');
    }

    public function testMissingExemptionReasonThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceLineData())->setTaxCategory('E');
    }

    public function testReasonCodeFromOtherCategoryThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceLineData())->setTaxCategory('E', 'VATEX-SA-32');
    }

    public function testOutOfScopeRequiresReasonText()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceLineData())->setTaxCategory('O', 'VATEX-SA-OOS');
    }

    /**
     * Mixed categories produce one TaxSubtotal per category; Z/E/O carry the
     * exemption reason, and O carries no rate.
     */
    public function testMixedCategoriesProduceVatBreakdown()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->standard()
            ->setInvoiceNumber('INV-MIX-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $invoiceData->addLine($this->categoryLine(1, 200.00));
        $invoiceData->addLine($this->categoryLine(2, 50.00, 'Z', 'VATEX-SA-32'));
        $invoiceData->addLine($this->categoryLine(3, 30.00, 'E', 'VATEX-SA-29'));
        $invoiceData->addLine($this->categoryLine(4, 20.00, 'O', 'VATEX-SA-OOS', 'Government fee'));
        $invoiceData->calculateTotals();

        $this->assertEqualsWithDelta(30.00, $invoiceData->getTaxTotalAmount(), 0.001);
        $this->assertEqualsWithDelta(330.00, $invoiceData->getPayableAmount(), 0.001);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $sub = '//cac:TaxTotal/cac:TaxSubtotal';

        $this->assertEquals(4, $xpath->evaluate("count($sub)"));

        $this->assertEquals('200.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='S']/cbc:TaxableAmount)"));
        $this->assertEquals('30.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='S']/cbc:TaxAmount)"));
        $this->assertEquals(0, $xpath->evaluate("count({$sub}[cac:TaxCategory/cbc:ID='S']/cac:TaxCategory/cbc:TaxExemptionReasonCode)"));

        $this->assertEquals('50.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='Z']/cbc:TaxableAmount)"));
        $this->assertEquals('0.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='Z']/cac:TaxCategory/cbc:Percent)"));
        $this->assertEquals('VATEX-SA-32', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='Z']/cac:TaxCategory/cbc:TaxExemptionReasonCode)"));
        $this->assertEquals('Export of goods', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='Z']/cac:TaxCategory/cbc:TaxExemptionReason)"));

        $this->assertEquals('VATEX-SA-29', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='E']/cac:TaxCategory/cbc:TaxExemptionReasonCode)"));

        $this->assertEquals(0, $xpath->evaluate("count({$sub}[cac:TaxCategory/cbc:ID='O']/cac:TaxCategory/cbc:Percent)"));
        $this->assertEquals('Government fee', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='O']/cac:TaxCategory/cbc:TaxExemptionReason)"));

        // Line-level categories; exemption reasons belong to the breakdown only.
        $this->assertEquals('Z', $xpath->evaluate("string(//cac:InvoiceLine[cbc:ID='2']/cac:Item/cac:ClassifiedTaxCategory/cbc:ID)"));
        $this->assertEquals(0, $xpath->evaluate('count(//cac:ClassifiedTaxCategory/cbc:TaxExemptionReasonCode)'));
        $this->assertEquals(0, $xpath->evaluate("count(//cac:InvoiceLine[cbc:ID='4']/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent)"));
    }

    /**
     * An all-exempt invoice must not gain a standard-rated category anywhere
     * (e.g. on the default document-level allowance, BR-S-01).
     */
    public function testExemptOnlyInvoiceHasNoStandardCategory()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->simplified()
            ->setInvoiceNumber('INV-E-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $invoiceData->addLine($this->categoryLine(1, 100.00, 'E', 'VATEX-SA-30'));
        $invoiceData->calculateTotals();

        $this->assertEquals(0.0, $invoiceData->getTaxTotalAmount());

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        $this->assertEquals(0, $xpath->evaluate("count(//cac:TaxCategory[cbc:ID='S'] | //cac:ClassifiedTaxCategory[cbc:ID='S'])"));
        $this->assertEquals('E', $xpath->evaluate('string(/*/cac:AllowanceCharge/cac:TaxCategory/cbc:ID)'));
    }

    /**
     * A document-level allowance reduces the taxable amount and VAT of its
     * own category only, and is emitted with ChargeIndicator=false.
     */
    public function testDocumentAllowanceAppliesToItsCategory()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->standard()
            ->setInvoiceNumber('INV-DA-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $invoiceData->addLine($this->categoryLine(1, 200.00));
        $invoiceData->addLine($this->categoryLine(2, 50.00, 'Z', 'VATEX-SA-33'));
        $invoiceData->addAllowance(['amount' => 20.00, 'reason' => 'discount', 'tax_category' => 'S', 'tax_percent' => 15.0]);
        $invoiceData->calculateTotals();

        $this->assertEqualsWithDelta(27.00, $invoiceData->getTaxTotalAmount(), 0.001);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $sub = '//cac:TaxTotal/cac:TaxSubtotal';

        $this->assertEquals('180.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='S']/cbc:TaxableAmount)"));
        $this->assertEquals('27.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='S']/cbc:TaxAmount)"));
        $this->assertEquals('50.00', $xpath->evaluate("string({$sub}[cac:TaxCategory/cbc:ID='Z']/cbc:TaxableAmount)"));

        $this->assertEquals('false', $xpath->evaluate('string(/*/cac:AllowanceCharge/cbc:ChargeIndicator)'));
        $this->assertEquals('SAR', $xpath->evaluate('string(/*/cac:AllowanceCharge/cbc:Amount/@currencyID)'));
        $this->assertEquals('S', $xpath->evaluate('string(/*/cac:AllowanceCharge/cac:TaxCategory/cbc:ID)'));
    }

    /**
     * LegalMonetaryTotal: LineExtensionAmount is the sum of line net amounts
     * (BR-CO-10) and TaxExclusiveAmount = LineExtension - allowances + charges
     * (BR-CO-13), with ChargeTotalAmount emitted when charges exist.
     */
    public function testMonetaryTotalsWithDocumentAllowanceAndCharge()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->standard()
            ->setInvoiceNumber('INV-LMT-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        $invoiceData->addLine($this->categoryLine(1, 200.00));
        $invoiceData->addLine($this->categoryLine(2, 100.00));
        $invoiceData->addAllowance(['amount' => 10.00, 'reason' => 'discount']);
        $invoiceData->addCharge(['amount' => 5.00, 'reason' => 'delivery', 'reason_code' => 'FC']);
        $invoiceData->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $lmt = '//cac:LegalMonetaryTotal';

        $this->assertEquals('300.00', $xpath->evaluate("string($lmt/cbc:LineExtensionAmount)"));
        $this->assertEquals('295.00', $xpath->evaluate("string($lmt/cbc:TaxExclusiveAmount)"));
        $this->assertEquals('10.00', $xpath->evaluate("string($lmt/cbc:AllowanceTotalAmount)"));
        $this->assertEquals('5.00', $xpath->evaluate("string($lmt/cbc:ChargeTotalAmount)"));
        $this->assertEquals('339.25', $xpath->evaluate("string($lmt/cbc:TaxInclusiveAmount)"));
        $this->assertEquals('339.25', $xpath->evaluate("string($lmt/cbc:PayableAmount)"));
    }

    public function testNoChargeTotalWithoutCharges()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->addLine($this->categoryLine(1, 100.00));
        $invoiceData->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        $this->assertEquals('100.00', $xpath->evaluate('string(//cac:LegalMonetaryTotal/cbc:LineExtensionAmount)'));
        $this->assertEquals(0, $xpath->evaluate('count(//cac:LegalMonetaryTotal/cbc:ChargeTotalAmount)'));
    }

    /**
     * BR-KSA-26: the first invoice's PIH is base64 of the hex SHA-256 of "0".
     */
    public function testFirstInvoiceHashMatchesBrKsa26()
    {
        $this->assertEquals(base64_encode(hash('sha256', '0')), InvoiceData::FIRST_INVOICE_HASH);
        $this->assertEquals(InvoiceData::FIRST_INVOICE_HASH, (new InvoiceData())->getPreviousInvoiceHash());
    }

    public function testLegacyMaPihIsConverted()
    {
        $invoiceData = (new InvoiceData())->setPreviousInvoiceHash('MA==');
        $this->assertEquals(InvoiceData::FIRST_INVOICE_HASH, $invoiceData->getPreviousInvoiceHash());

        $hash = base64_encode(hash('sha256', 'previous invoice', true));
        $invoiceData->setPreviousInvoiceHash($hash);
        $this->assertEquals($hash, $invoiceData->getPreviousInvoiceHash());
    }

    public function testFirstInvoicePihInXml()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->addLine($this->categoryLine(1, 100.00));
        $invoiceData->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        $this->assertEquals(
            InvoiceData::FIRST_INVOICE_HASH,
            $xpath->evaluate("string(//cac:AdditionalDocumentReference[cbc:ID='PIH']/cac:Attachment/cbc:EmbeddedDocumentBinaryObject)")
        );
    }

    /**
     * Build a standard invoice around the given lines.
     */
    private function standardInvoice(InvoiceLineData ...$lines): InvoiceData
    {
        $invoiceData = new InvoiceData();
        $invoiceData->standard()
            ->setInvoiceNumber('INV-A-001')
            ->setIssueDate('2024-01-15')
            ->setIssueTime('10:30:00')
            ->setDocumentCurrencyCode('SAR');

        foreach ($lines as $line) {
            $invoiceData->addLine($line);
        }

        return $invoiceData;
    }

    public function testMoneyRoundsHalfUp()
    {
        $this->assertSame(10.01, \KhaledHajSalem\Zatca\Support\Money::round(3 * 3.335));
        $this->assertSame(123.49, \KhaledHajSalem\Zatca\Support\Money::round(123.4949));
        $this->assertSame(123.50, \KhaledHajSalem\Zatca\Support\Money::round(123.4951));
        $this->assertSame('3.335', \KhaledHajSalem\Zatca\Support\Money::format(3.335));
        $this->assertSame('3.00', \KhaledHajSalem\Zatca\Support\Money::format(3));
    }

    /**
     * Line and document amounts are rounded before being summed, so the
     * printed totals add up (§9.3, §10, BR-CO-10/13/15, BR-KSA-51).
     */
    public function testRoundedTotalsAddUp()
    {
        $line = new InvoiceLineData();
        $line->setId(1)->setItemName('Widget')->setQuantity(3)->setUnitPrice(3.335)->setTaxPercent(15.0)
            ->setChargeAmount(2.00)->setChargeReason('Cleaning')->setChargeReasonCode('CG')
            ->calculateTotals();

        $invoiceData = $this->standardInvoice($line);
        $invoiceData->addCharge(['amount' => 5.00, 'reason' => 'Freight', 'reason_code' => 'FC']);
        $invoiceData->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $num = fn(string $q) => (float)$xpath->evaluate("string($q)");

        $lineNet = $num('//cac:InvoiceLine/cbc:LineExtensionAmount');
        $lineVat = $num('//cac:InvoiceLine/cac:TaxTotal/cbc:TaxAmount');
        $this->assertEquals(12.01, $lineNet);                                        // 10.01 + 2.00
        $this->assertEqualsWithDelta($lineNet + $lineVat, $num('//cac:InvoiceLine/cac:TaxTotal/cbc:RoundingAmount'), 0.001);

        $lmt = '//cac:LegalMonetaryTotal';
        $this->assertEqualsWithDelta($lineNet, $num("$lmt/cbc:LineExtensionAmount"), 0.001);
        $this->assertEqualsWithDelta($num("$lmt/cbc:LineExtensionAmount") + $num("$lmt/cbc:ChargeTotalAmount") - $num("$lmt/cbc:AllowanceTotalAmount"), $num("$lmt/cbc:TaxExclusiveAmount"), 0.001);
        $this->assertEqualsWithDelta($num("$lmt/cbc:TaxExclusiveAmount") + $num('//cac:TaxTotal[cac:TaxSubtotal]/cbc:TaxAmount'), $num("$lmt/cbc:TaxInclusiveAmount"), 0.001);
        $this->assertEquals('2.55', $xpath->evaluate('string(//cac:TaxSubtotal/cbc:TaxAmount)'));  // 17.01 × 15% = 2.5515
    }

    /**
     * Line charges are written as cac:AllowanceCharge with a reason code
     * (BR-KSA-EN16931-11, BR-KSA-20/22).
     */
    public function testLineChargeIsEmitted()
    {
        $line = new InvoiceLineData();
        $line->setId(1)->setItemName('Widget')->setQuantity(1)->setUnitPrice(100)->setTaxPercent(15.0)
            ->setChargeAmount(10.00)->setChargeReason('Cleaning')->setChargeReasonCode('CG')
            ->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($this->standardInvoice($line)));
        $charge = "//cac:InvoiceLine/cac:AllowanceCharge[cbc:ChargeIndicator='true']";

        $this->assertEquals('CG', $xpath->evaluate("string($charge/cbc:AllowanceChargeReasonCode)"));
        $this->assertEquals('Cleaning', $xpath->evaluate("string($charge/cbc:AllowanceChargeReason)"));
        $this->assertEquals('10.00', $xpath->evaluate("string($charge/cbc:Amount)"));
        $this->assertEquals('110.00', $xpath->evaluate('string(//cac:InvoiceLine/cbc:LineExtensionAmount)'));
    }

    public function testLineChargeWithoutReasonCodeThrows()
    {
        $line = new InvoiceLineData();
        $line->setId(1)->setItemName('Widget')->setQuantity(1)->setUnitPrice(100)->setTaxPercent(15.0)
            ->setChargeAmount(10.00)->calculateTotals();

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($this->standardInvoice($line));
    }

    public function testDocumentChargeRequiresReasonCode()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceData())->addCharge(['amount' => 5.00, 'reason' => 'Freight']);
    }

    public function testDocumentChargeReasonCodeEmitted()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->addCharge(['amount' => 5.00, 'reason' => 'Freight', 'reason_code' => 'FC']);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $charge = "/*/cac:AllowanceCharge[cbc:ChargeIndicator='true']";

        // UBL order: ChargeIndicator, AllowanceChargeReasonCode, AllowanceChargeReason.
        $this->assertEquals('FC', $xpath->evaluate("string($charge/cbc:ChargeIndicator/following-sibling::*[1][self::cbc:AllowanceChargeReasonCode])"));
        $this->assertEquals('Freight', $xpath->evaluate("string($charge/cbc:AllowanceChargeReason)"));
    }

    public function testAllowanceAmountComputedFromBaseAndPercentage()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->addAllowance(['base_amount' => 200.00, 'multiplier' => 10]);

        $this->assertEquals(20.00, $invoiceData->getAllowances()[0]['amount']);
    }

    public function testAllowanceInconsistentAmountThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceData())->addAllowance(['base_amount' => 200.00, 'multiplier' => 10, 'amount' => 25.00]);
    }

    public function testAllowanceBaseWithoutPercentageThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceData())->addAllowance(['base_amount' => 200.00, 'amount' => 20.00]);
    }

    /**
     * Unit price and quantity keep their decimals (§7.3); unitCode comes from
     * the line (§11.2.6).
     */
    public function testUnitPriceQuantityAndUnitCode()
    {
        $line = new InvoiceLineData();
        $line->setId(1)->setItemName('Fuel')->setQuantity(2.125)->setUnitPrice(2.335)->setUnitCode('LTR')
            ->setTaxPercent(15.0)->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($this->standardInvoice($line)));

        $this->assertEquals('2.335', $xpath->evaluate('string(//cac:Price/cbc:PriceAmount)'));
        $this->assertEquals('SAR', $xpath->evaluate('string(//cac:Price/cbc:PriceAmount/@currencyID)'));
        $this->assertEquals('2.125', $xpath->evaluate('string(//cbc:InvoicedQuantity)'));
        $this->assertEquals('LTR', $xpath->evaluate('string(//cbc:InvoicedQuantity/@unitCode)'));
        $this->assertEquals('4.96', $xpath->evaluate('string(//cac:InvoiceLine/cbc:LineExtensionAmount)')); // 4.961875
    }

    public function testDefaultUnitCodeIsPce()
    {
        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($this->standardInvoice($this->categoryLine(1, 100.00))));

        $this->assertEquals('PCE', $xpath->evaluate('string(//cbc:InvoicedQuantity/@unitCode)'));
    }

    /**
     * No empty elements (BR-KSA-F-03), and PaymentMeans follows UBL order.
     */
    public function testNoEmptyElementsAndPaymentMeansOrder()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->creditNote();
        $invoiceData->addBillingReference(['id' => 'INV-1']);
        $invoiceData->addPaymentMeans(['code' => '42', 'channel_code' => 'SWIFT', 'instruction_note' => 'Returned goods', 'payment_id' => 'PAY-1']);

        $seller = new SellerData();
        $seller->setRegistrationName('Seller')->setVatNumber('300000000000003');
        $invoiceData->setSeller($seller);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        // Only the signer placeholders (ExtensionContent, QR) may be empty before signing.
        $empty = $xpath->query("//*[not(*) and normalize-space(.)='' and not(self::ext:ExtensionContent) and not(ancestor::cac:AdditionalDocumentReference[cbc:ID='QR'])]");
        $this->assertEquals(0, $empty->length, 'Empty elements: ' . implode(', ', array_map(fn($n) => $n->nodeName, iterator_to_array($empty))));

        $order = array_map(fn($n) => $n->localName, iterator_to_array($xpath->query('//cac:PaymentMeans/*')));
        $this->assertEquals(['ID', 'PaymentMeansCode', 'PaymentChannelCode', 'InstructionNote', 'PaymentID'], $order);
    }

    /**
     * Invoice flags (KSA-2 positions 3-7) don't affect subtype detection.
     */
    public function testInvoiceFlagsKeepSubtype()
    {
        $invoiceData = (new InvoiceData())->setInvoiceTypeName('0100100'); // export tax invoice

        $this->assertTrue($invoiceData->isStandard());
        $this->assertFalse($invoiceData->isSimplified());

        $invoiceData->simplified();
        $this->assertEquals('0200100', $invoiceData->getInvoiceTypeName());
        $this->assertTrue($invoiceData->isSimplified());
    }

    /**
     * Only BT-111 is in the tax currency; the breakdown is in the invoice
     * currency (BR-KSA-CL-02).
     */
    public function testForeignCurrencyInvoice()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->setDocumentCurrencyCode('USD')->setTaxCurrencyCode('SAR')->setTaxExchangeRate(3.75);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        $this->assertEquals('SAR', $xpath->evaluate('string(//cac:TaxTotal[not(cac:TaxSubtotal)]/cbc:TaxAmount/@currencyID)'));
        $this->assertEquals('56.25', $xpath->evaluate('string(//cac:TaxTotal[not(cac:TaxSubtotal)]/cbc:TaxAmount)'));
        $this->assertEquals('USD', $xpath->evaluate('string(//cac:TaxTotal[cac:TaxSubtotal]/cbc:TaxAmount/@currencyID)'));
        $this->assertEquals('15.00', $xpath->evaluate('string(//cac:TaxTotal[cac:TaxSubtotal]/cbc:TaxAmount)'));
        $this->assertEquals(0, $xpath->evaluate("count(//*[@currencyID='SAR'])") - 1);
    }

    public function testForeignCurrencyWithoutExchangeRateThrows()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->setDocumentCurrencyCode('USD');

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    // ---------------------------------------------------------------
    // Transaction flags (KSA-2) and supply dates
    // ---------------------------------------------------------------

    public function testTransactionFlags()
    {
        $invoiceData = (new InvoiceData())->standard()->thirdParty()->nominal()->summary();

        $this->assertEquals('0111010', $invoiceData->getInvoiceTypeName());
        $this->assertEquals('0111010', $invoiceData->getTransactionCode());
        $this->assertTrue($invoiceData->isThirdParty());
        $this->assertTrue($invoiceData->isNominal());
        $this->assertFalse($invoiceData->isExport());
        $this->assertTrue($invoiceData->isSummary());
        $this->assertFalse($invoiceData->isSelfBilled());

        $invoiceData->nominal(false)->export()->summary(false)->selfBilled();
        $this->assertEquals('0110101', $invoiceData->getInvoiceTypeName());
    }

    public function testInvalidTransactionCodeThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceData())->setTransactionCode('0300000');
    }

    public function testExportSelfBilledThrows()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->export()->selfBilled();

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    public function testSimplifiedExportThrows()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->simplified()->export();

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    public function testExportWithBuyerVatThrows()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00, 'Z', 'VATEX-SA-32'));
        $invoiceData->export()->setBuyer((new BuyerData())->setRegistrationName('Foreign Co')->setVatNumber('311111111111113'));

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    public function testSupplyDates()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->summary()->setSupplyDate('2024-01-01')->setSupplyEndDate('2024-01-31');

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        $this->assertEquals('0100010', $xpath->evaluate('string(//cbc:InvoiceTypeCode/@name)'));
        $this->assertEquals('2024-01-01', $xpath->evaluate('string(//cac:Delivery/cbc:ActualDeliveryDate)'));
        $this->assertEquals('2024-01-31', $xpath->evaluate('string(//cac:Delivery/cbc:LatestDeliveryDate)'));
    }

    public function testSupplyDateDefaultsToIssueDate()
    {
        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($this->standardInvoice($this->categoryLine(1, 100.00))));

        $this->assertEquals('2024-01-15', $xpath->evaluate('string(//cac:Delivery/cbc:ActualDeliveryDate)'));
        $this->assertEquals(0, $xpath->evaluate('count(//cac:Delivery/cbc:LatestDeliveryDate)'));
    }

    public function testSupplyEndBeforeStartThrows()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->setSupplyDate('2024-01-31')->setSupplyEndDate('2024-01-01');

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    public function testSimplifiedSummaryRequiresEndDate()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->simplified()->summary()->setBuyer((new BuyerData())->setRegistrationName('Customer'));

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    public function testSimplifiedSummaryRequiresBuyerName()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->simplified()->summary()->setSupplyEndDate('2024-01-31');

        $this->expectException(\InvalidArgumentException::class);
        (new ZatcaInvoice())->generateXml($invoiceData);
    }

    // ---------------------------------------------------------------
    // Prepayment adjustment (§9.5)
    // ---------------------------------------------------------------

    /**
     * Mirrors the standard's §9.5 example: 200 + 15% = 230, less a 100
     * prepayment (86.96 + 13.04) leaves 130 payable.
     */
    public function testPrepaymentAdjustment()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 200.00));
        $invoiceData->addPrepayment([
            'id' => '46531', 'issue_date' => '2021-07-31', 'issue_time' => '12:28:17', 'taxable_amount' => 86.96,
        ]);
        $invoiceData->calculateTotals();

        $this->assertEqualsWithDelta(100.00, $invoiceData->getPrepaidAmount(), 0.001);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $lmt = '//cac:LegalMonetaryTotal';

        $this->assertEquals('200.00', $xpath->evaluate("string($lmt/cbc:LineExtensionAmount)"));
        $this->assertEquals('230.00', $xpath->evaluate("string($lmt/cbc:TaxInclusiveAmount)"));
        $this->assertEquals('100.00', $xpath->evaluate("string($lmt/cbc:PrepaidAmount)"));
        $this->assertEquals('130.00', $xpath->evaluate("string($lmt/cbc:PayableAmount)"));

        // Document VAT breakdown is unaffected by the prepayment line.
        $this->assertEquals('30.00', $xpath->evaluate('string(/*/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount)'));
        $this->assertEquals('2', $xpath->evaluate('string(//cbc:LineCountNumeric)'));

        $line = "//cac:InvoiceLine[cac:DocumentReference]";
        $this->assertEquals('2', $xpath->evaluate("string($line/cbc:ID)"));
        $this->assertEquals('0.00', $xpath->evaluate("string($line/cbc:LineExtensionAmount)"));
        $this->assertEquals('46531', $xpath->evaluate("string($line/cac:DocumentReference/cbc:ID)"));
        $this->assertEquals('2021-07-31', $xpath->evaluate("string($line/cac:DocumentReference/cbc:IssueDate)"));
        $this->assertEquals('12:28:17', $xpath->evaluate("string($line/cac:DocumentReference/cbc:IssueTime)"));
        $this->assertEquals('386', $xpath->evaluate("string($line/cac:DocumentReference/cbc:DocumentTypeCode)"));
        $this->assertEquals('86.96', $xpath->evaluate("string($line/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount)"));
        $this->assertEquals('13.04', $xpath->evaluate("string($line/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount)"));
        $this->assertEquals('S', $xpath->evaluate("string($line/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:ID)"));
        $this->assertEquals('15.00', $xpath->evaluate("string($line/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:Percent)"));
        $this->assertEquals('0.00', $xpath->evaluate("string($line/cac:Price/cbc:PriceAmount)"));
    }

    public function testExemptPrepaymentHasZeroRate()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->addPrepayment([
            'id' => 'P-1', 'issue_date' => '2024-01-01', 'issue_time' => '10:00:00',
            'taxable_amount' => 40.00, 'tax_category' => 'E', 'tax_percent' => 15,
        ]);

        $prepayment = $invoiceData->getPrepayments()[0];
        $this->assertEquals(0.0, $prepayment['tax_percent']);
        $this->assertEquals(0.0, $prepayment['tax_amount']);
    }

    public function testPrepaymentMissingDateThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceData())->addPrepayment(['id' => 'P-1', 'issue_time' => '10:00:00', 'taxable_amount' => 40.00]);
    }

    public function testPrepaymentWrongTaxAmountThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new InvoiceData())->addPrepayment([
            'id' => 'P-1', 'issue_date' => '2024-01-01', 'issue_time' => '10:00:00', 'taxable_amount' => 100.00, 'tax_amount' => 10.00,
        ]);
    }

    // ---------------------------------------------------------------
    // Optional fields
    // ---------------------------------------------------------------

    public function testNoteAndPayableRounding()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 99.99));
        $invoiceData->setNote('Self-billing statement', 'en')->setPayableRoundingAmount(0.01);

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));
        $lmt = '//cac:LegalMonetaryTotal';

        $this->assertEquals('Self-billing statement', $xpath->evaluate('string(/*/cbc:Note)'));
        $this->assertEquals('en', $xpath->evaluate('string(/*/cbc:Note/@languageID)'));
        $this->assertEquals('InvoiceTypeCode', $xpath->evaluate('local-name(/*/cbc:Note/preceding-sibling::*[1])'));

        $this->assertEquals('114.99', $xpath->evaluate("string($lmt/cbc:TaxInclusiveAmount)"));     // 99.99 + 15.00
        $this->assertEquals('0.01', $xpath->evaluate("string($lmt/cbc:PayableRoundingAmount)"));
        $this->assertEquals('115.00', $xpath->evaluate("string($lmt/cbc:PayableAmount)"));
        $this->assertEquals('PrepaidAmount', $xpath->evaluate("local-name($lmt/cbc:PayableRoundingAmount/preceding-sibling::*[1])"));
    }

    public function testAdditionalStreetName()
    {
        $invoiceData = $this->standardInvoice($this->categoryLine(1, 100.00));
        $invoiceData->setSeller((new SellerData())->setRegistrationName('S')->setStreetName('Main')->setAdditionalStreetName('Unit 5'));
        $invoiceData->setBuyer((new BuyerData())->setRegistrationName('B')->setStreetName('Side')->setAdditionalStreetName('Floor 2'));

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($invoiceData));

        $this->assertEquals('Unit 5', $xpath->evaluate('string(//cac:AccountingSupplierParty//cbc:StreetName/following-sibling::*[1][self::cbc:AdditionalStreetName])'));
        $this->assertEquals('Floor 2', $xpath->evaluate('string(//cac:AccountingCustomerParty//cbc:AdditionalStreetName)'));
    }

    /**
     * Gross price and per-unit discount on the price (BT-146/147/148),
     * with base quantity (BT-149), per the standard's §9.3 example.
     */
    public function testPriceDiscountAndBaseQuantity()
    {
        $line = new InvoiceLineData();
        $line->setId(1)->setItemName('Item')->setQuantity(10)->setUnitPrice(500)->setPriceDiscount(50)
            ->setBaseQuantity(2)->setTaxPercent(15.0)->calculateTotals();

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($this->standardInvoice($line)));
        $price = '//cac:InvoiceLine/cac:Price';

        $this->assertEquals('450.00', $xpath->evaluate("string($price/cbc:PriceAmount)"));
        $this->assertEquals('2.00', $xpath->evaluate("string($price/cbc:BaseQuantity)"));
        $this->assertEquals('false', $xpath->evaluate("string($price/cac:AllowanceCharge/cbc:ChargeIndicator)"));
        $this->assertEquals('50.00', $xpath->evaluate("string($price/cac:AllowanceCharge/cbc:Amount)"));
        $this->assertEquals('500.00', $xpath->evaluate("string($price/cac:AllowanceCharge/cbc:BaseAmount)"));
        $this->assertEquals('2250.00', $xpath->evaluate('string(//cac:InvoiceLine/cbc:LineExtensionAmount)')); // 10 × 450 / 2
    }

    public function testItemDescriptionAndIdentifiers()
    {
        $line = $this->categoryLine(1, 100.00);
        $line->setDescription('Blue, size M')->setItemCode('SKU-1')->setBuyersItemCode('B-9')->setStandardItemCode('6281000000000', '0160');

        $xpath = $this->xpath((new ZatcaInvoice())->generateXml($this->standardInvoice($line)));
        $item = '//cac:InvoiceLine/cac:Item';

        $order = array_map(fn($n) => $n->localName, iterator_to_array($xpath->query("$item/*")));
        $this->assertEquals(['Description', 'Name', 'BuyersItemIdentification', 'SellersItemIdentification', 'StandardItemIdentification', 'ClassifiedTaxCategory'], $order);
        $this->assertEquals('SKU-1', $xpath->evaluate("string($item/cac:SellersItemIdentification/cbc:ID)"));
        $this->assertEquals('0160', $xpath->evaluate("string($item/cac:StandardItemIdentification/cbc:ID/@schemeID)"));
    }

    public function testFileName()
    {
        $invoiceData = (new InvoiceData())->setInvoiceNumber('2021/05/26/23555')->setIssueDate('2021-05-26')->setIssueTime('13:24:00');
        $invoiceData->setSeller((new SellerData())->setVatNumber('300000000000003'));

        $this->assertEquals('300000000000003_20210526T132400_2021-05-26-23555.xml', $invoiceData->getFileName());
    }

    public function testAllowanceWithUnmatchedExemptCategoryThrows()
    {
        $invoiceData = new InvoiceData();
        $invoiceData->addLine($this->categoryLine(1, 100.00));
        $invoiceData->addAllowance(['amount' => 10.00, 'tax_category' => 'E']);

        $this->expectException(\InvalidArgumentException::class);
        $invoiceData->calculateTotals();
    }
}
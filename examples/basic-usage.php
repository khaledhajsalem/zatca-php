<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KhaledHajSalem\Zatca\ZatcaManager;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;

// Example: Basic ZATCA Invoice Processing

try {
    // 1. Initialize ZATCA Manager
    $zatcaManager = new ZatcaManager([
        'environment' => 'sandbox', // or 'simulation', 'production'
        'certificate_path' => __DIR__ . '/../storage/certificate.pem',
        'private_key_path' => __DIR__ . '/../storage/private.pem',
        'secret' => 'CkYsEXfV8c1gFHAtFWoZv73pGMvh/Qyo4LzKM2h/8Hg=' // This is the secret key for the ZATCA API
    ]);

    // 2. Create invoice data
    $invoiceData = new InvoiceData();
    $invoiceData->setInvoiceNumber('INV-001')
        ->setIssueDate('2025-07-15')
        ->setIssueTime('10:30:00')
        ->setDueDate('2025-09-15')
        ->setCurrencyCode('SAR')
        ->setInvoiceTypeCode('388') // Standard Tax Invoice
        ->setInvoiceTypeName('0100000') // Standard Tax Invoice (requires clearance)
        ->setDocumentCurrencyCode('SAR');

    // 3. Set seller information
    $seller = new SellerData();
    $seller->setRegistrationName('Your Company Name')
        ->setVatNumber('399999999900003')
        ->setPartyIdentification('1010203020')
        ->setPartyIdentificationId('CRN') // Commercial Registration Number
        ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
        ->setCountryCode('SA')
        ->setCityName('Riyadh')
        ->setPostalZone('12345')
        ->setStreetName('Main Street')
        ->setBuildingNumber('1234')
        ->setPlotIdentification('PLOT-001')
        ->setCitySubdivisionName('District 1');

    $invoiceData->setSeller($seller);

    // 4. Set buyer information
    $buyer = new BuyerData();
    $buyer->setRegistrationName('Customer Company')
        ->setVatNumber('300000000000003')
        ->setPartyIdentification('300000000000003')
        ->setPartyIdentificationId('TIN') // Tax Identification Number
        ->setAddress('456 Customer Street, Jeddah, Saudi Arabia')
        ->setCountryCode('SA')
        ->setCityName('Jeddah')
        ->setPostalZone('54321')
        ->setStreetName('Customer Street')
        ->setBuildingNumber('4567')
        ->setPlotIdentification('PLOT-002')
        ->setCitySubdivisionName('District 2');

    $invoiceData->setBuyer($buyer);

    // 5. Add invoice lines
    $line1 = new InvoiceLineData();
    $line1->setId(1)
        ->setItemName('Product 1')
        ->setDescription('High-quality product')
        ->setQuantity(2)
        ->setUnitPrice(100.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $line2 = new InvoiceLineData();
    $line2->setId(2)
        ->setItemName('Product 2')
        ->setDescription('Premium service')
        ->setQuantity(1)
        ->setUnitPrice(50.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $invoiceData->addLine($line1);
    $invoiceData->addLine($line2);

    // 6. Calculate invoice totals
    $invoiceData->calculateTotals();

    // 7. Process the invoice
    $result = $zatcaManager->processInvoice($invoiceData);

    // 8. Display results
    echo "Invoice processed successfully!\n";
    echo "QR Code: " . $result['qr_code'] . "\n";
    echo "Invoice Hash: " . $result['invoice_hash'] . "\n";
    echo "UUID: " . $result['uuid'] . "\n";
    echo "Is Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    
    // Save the signed XML to a file
    file_put_contents('signed-invoice.xml', $result['xml']);
    echo "Signed XML saved to: signed-invoice.xml\n";

} catch (ZatcaException $e) {
    echo "ZATCA Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
} 
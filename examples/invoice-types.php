<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KhaledHajSalem\Zatca\ZatcaManager;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;

// Example: Different Invoice Types and Clearance Requirements

/**
 * ZATCA Invoice Types:
 * 
 * 1. Standard Tax Invoice (B2B/B2G):
 *    - invoice_type_code = 388
 *    - invoice_type_name = "0100000"
 *    - Requires clearance before distribution
 * 
 * 2. Simplified Tax Invoice (B2C):
 *    - invoice_type_code = 388
 *    - invoice_type_name = "0200000"
 *    - No clearance required, report within 24 hours
 * 
 * 3. Debit Note:
 *    - invoice_type_code = 383
 *    - No clearance required
 * 
 * 4. Credit Note:
 *    - invoice_type_code = 381
 *    - No clearance required
 * 
 * 5. Prepayment Invoice:
 *    - invoice_type_code = 386
 *    - No clearance required
 */

try {
    // Initialize ZATCA Manager
    $zatcaManager = new ZatcaManager([
        'environment' => 'sandbox',
        'certificate_path' => '/path/to/your/certificate.pem',
        'private_key_path' => '/path/to/your/private.pem',
        'secret' => 'your-secret-key'
    ]);

    // Set up common seller and buyer data
    $seller = new SellerData();
    $seller->setRegistrationName('Your Company Name')
        ->setVatNumber('123456789012345')
        ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
        ->setCountryCode('SA');

    $buyer = new BuyerData();
    $buyer->setRegistrationName('Customer Company')
        ->setVatNumber('987654321098765')
        ->setAddress('456 Customer Street, Jeddah, Saudi Arabia')
        ->setCountryCode('SA');

    // Example 1: Standard Tax Invoice (B2B) - Requires Clearance
    echo "=== Standard Tax Invoice (B2B) - Requires Clearance ===\n";
    $standardInvoice = new InvoiceData();
    $standardInvoice->setInvoiceNumber('INV-001')
        ->setIssueDate('2025-07-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->setInvoiceTypeCode('388')
        ->setInvoiceTypeName('0100000') // Standard Tax Invoice
        ->setSeller($seller)
        ->setBuyer($buyer);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Product 1')
        ->setQuantity(2)
        ->setUnitPrice(100.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $standardInvoice->addLine($line);
    $standardInvoice->calculateTotals();

    $result = $zatcaManager->processInvoice($standardInvoice);
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "QR Code: " . $result['qr_code'] . "\n\n";

    // Example 2: Simplified Tax Invoice (B2C) - No Clearance Required
    echo "=== Simplified Tax Invoice (B2C) - No Clearance Required ===\n";
    $simplifiedInvoice = new InvoiceData();
    $simplifiedInvoice->setInvoiceNumber('INV-002')
        ->setIssueDate('2025-07-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->setInvoiceTypeCode('388')
        ->setInvoiceTypeName('0200000') // Simplified Tax Invoice
        ->setSeller($seller)
        ->setBuyer($buyer);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Product 2')
        ->setQuantity(1)
        ->setUnitPrice(50.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $simplifiedInvoice->addLine($line);
    $simplifiedInvoice->calculateTotals();

    $result = $zatcaManager->processInvoice($simplifiedInvoice);
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "QR Code: " . $result['qr_code'] . "\n\n";

    // Example 3: Debit Note - No Clearance Required
    echo "=== Debit Note - No Clearance Required ===\n";
    $debitNote = new InvoiceData();
    $debitNote->setInvoiceNumber('DN-001')
        ->setIssueDate('2024-01-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->setInvoiceTypeCode('383') // Debit Note
        ->setSeller($seller)
        ->setBuyer($buyer);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Additional Charge')
        ->setQuantity(1)
        ->setUnitPrice(25.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $debitNote->addLine($line);
    $debitNote->calculateTotals();

    $result = $zatcaManager->processInvoice($debitNote);
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "QR Code: " . $result['qr_code'] . "\n\n";

    // Example 4: Credit Note - No Clearance Required
    echo "=== Credit Note - No Clearance Required ===\n";
    $creditNote = new InvoiceData();
    $creditNote->setInvoiceNumber('CN-001')
        ->setIssueDate('2024-01-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->setInvoiceTypeCode('381') // Credit Note
        ->setSeller($seller)
        ->setBuyer($buyer);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Return Credit')
        ->setQuantity(1)
        ->setUnitPrice(-30.00) // Negative amount for credit
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $creditNote->addLine($line);
    $creditNote->calculateTotals();

    $result = $zatcaManager->processInvoice($creditNote);
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "QR Code: " . $result['qr_code'] . "\n\n";

    // Example 5: Prepayment Invoice - No Clearance Required
    echo "=== Prepayment Invoice - No Clearance Required ===\n";
    $prepaymentInvoice = new InvoiceData();
    $prepaymentInvoice->setInvoiceNumber('PP-001')
        ->setIssueDate('2024-01-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->setInvoiceTypeCode('386') // Prepayment Invoice
        ->setSeller($seller)
        ->setBuyer($buyer);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Advance Payment')
        ->setQuantity(1)
        ->setUnitPrice(500.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $prepaymentInvoice->addLine($line);
    $prepaymentInvoice->calculateTotals();

    $result = $zatcaManager->processInvoice($prepaymentInvoice);
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "QR Code: " . $result['qr_code'] . "\n\n";

    echo "All invoice types processed successfully!\n";

} catch (ZatcaException $e) {
    echo "ZATCA Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
} 
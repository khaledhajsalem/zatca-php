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
 * Standard Tax Invoice (B2B/B2G):
 *    - invoice_type_name = "0100000"
 *    - Requires clearance before distribution
 * 
 * Simplified Tax Invoice (B2C):
 *    - invoice_type_name = "0200000"
 *    - No clearance required, report within 24 hours
 * 
 * Debit Note: (standard or simplified)
 *    - invoice_type_code = 383
 * 
 * Credit Note: (standard or simplified)
 *    - invoice_type_code = 381
 * 
 * Prepayment Invoice:
 *    - invoice_type_code = 386
 */

try {
    // Initialize ZATCA Manager
    $zatcaManager = new ZatcaManager([
        'environment' => 'sandbox',
        'certificate_path' =>  __DIR__ . '/../storage/certificate.pem',
        'private_key_path' => __DIR__ . '/../storage/private.pem',
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
        ->standard() // Standard Invoice
        ->taxInvoice() // Tax Invoice (388)
        ->setIssueDate('2025-09-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
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
    //
    // For a B2C invoice to an unidentified walk-in customer, simply omit the
    // buyer (do NOT call setBuyer). The package will not emit an empty
    // cac:AccountingCustomerParty. If you do have a buyer name/ID, set it and
    // only the provided fields are emitted (no empty postal address or tax
    // scheme for simplified invoices).
    //
    // This example also shows a line-level discount (BG-27): setUnitPrice() is
    // the GROSS unit price and setAllowanceAmount() is the total line discount.
    // The line is emitted as a cac:AllowanceCharge and LineExtensionAmount is
    // the net amount (50.00 - 5.00 = 45.00).
    echo "=== Simplified Tax Invoice (B2C) - No Clearance Required ===\n";
    $simplifiedInvoice = new InvoiceData();
    $simplifiedInvoice->setInvoiceNumber('INV-002')
        ->simplified() // Simplified Invoice
        ->taxInvoice() // Tax Invoice (388)
        ->setIssueDate('2025-09-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->setSeller($seller);
        // No setBuyer(): walk-in customer -> AccountingCustomerParty is omitted.

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Product 2')
        ->setQuantity(1)
        ->setUnitPrice(50.00)             // gross unit price
        ->setAllowanceAmount(5.00)        // total line discount
        ->setAllowanceReason('Promotional discount')
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $simplifiedInvoice->addLine($line);
    $simplifiedInvoice->calculateTotals();

    $result = $zatcaManager->processInvoice($simplifiedInvoice);
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "QR Code: " . $result['qr_code'] . "\n\n";

    // Example 3: Debit Note - No Clearance Required
    echo "=== Standard or Simplified Debit Note - Requires Clearance if Standard ===\n";
    $debitNote = new InvoiceData();
    $debitNote->setInvoiceNumber('DN-001')
        ->standard() // Standard Invoice or replace with simplified() for simplified invoice
        ->debitNote() // Debit Note (383)
        ->setIssueDate('2025-09-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->addBillingReference([ // add the original invoice reference in credit or debit notes
            'id' => 'INV-001',
            'uuid' => '63decc4e-cc4d-4e3b-878c-b772560bb5f1'
        ])
        ->addPaymentMeans([ // Payment Means is optional, add it in credit or debit notes
            'id' => '1234567890',
            'code' => '10',
            'due_date' => '2025-09-15',
            'instruction_note' => "Addition" // Addition, Correction, Returns, etc.
        ])
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
    echo "=== Standard or Simplified Credit Note - Requires Clearance if Standard ===\n";
    $creditNote = new InvoiceData();
    $creditNote->setInvoiceNumber('CN-001')
        ->standard() // Standard Invoice or replace with simplified() for simplified invoice
        ->creditNote() // Credit Note (381)
        ->setIssueDate('2025-09-15')
        ->setIssueTime('10:30:00')
        ->setCurrencyCode('SAR')
        ->addBillingReference([ // add the original invoice reference in credit or debit notes
            'id' => 'INV-001',
            'uuid' => '63decc4e-cc4d-4e3b-878c-b772560bb5f1'
        ])
        ->addPaymentMeans([ // Payment Means is optional, add it in credit or debit notes
            'id' => '1234567890',
            'code' => '10',
            'due_date' => '2025-09-15',
            'instruction_note' => "Returns" // Addition, Correction, Returns, Cancellation, etc.
        ])
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
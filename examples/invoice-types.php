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
 *    - Cleared by ZATCA before it is shared with the buyer
 *
 * Simplified Tax Invoice (B2C):
 *    - invoice_type_name = "0200000"
 *    - Reported to ZATCA within 24 hours
 *
 * Debit Note (383) / Credit Note (381):
 *    - Standard or simplified, like the invoice they correct
 *    - Must reference the original invoice and give the reason
 *    - Amounts are positive; the type code says it is a credit/debit
 *
 * Prepayment Invoice (386):
 *    - Advance payment; deducted later from the final invoice
 */

// Every invoice needs the next counter value (ICV) and the hash of the
// previous invoice (PIH). Persist both in your database in production.
$counter = 0;
$previousHash = InvoiceData::FIRST_INVOICE_HASH;

/**
 * Submit an invoice and advance the ICV/PIH chain.
 */
function submit(ZatcaManager $zatcaManager, InvoiceData $invoice, int &$counter, string &$previousHash): array
{
    $invoice->setInvoiceCounter((string)++$counter)
        ->setPreviousInvoiceHash($previousHash)
        ->calculateTotals();

    $result = $zatcaManager->processInvoice($invoice);
    $previousHash = $result['invoice_hash'];

    $response = $result['response'];
    echo "Clearance Required: " . ($result['is_clearance_required'] ? 'Yes' : 'No') . "\n";
    echo "Status: " . ($response['validationResults']['status'] ?? '-') . ' / '
        . ($response['clearanceStatus'] ?? $response['reportingStatus'] ?? '-') . "\n\n";

    return $result;
}

try {
    // Initialize ZATCA Manager (sandbox credentials from certificate-generation.php)
    $zatcaManager = new ZatcaManager([
        'environment' => 'sandbox',
        'certificate_path' => __DIR__ . '/../storage/certificate.pem',
        'private_key_path' => __DIR__ . '/../storage/private.pem',
        'secret' => trim(file_get_contents(__DIR__ . '/../storage/secret.txt')),
        'validate' => true,
    ]);

    $today = date('Y-m-d');
    $now = date('H:i:s');

    // Set up common seller and buyer data
    $seller = new SellerData();
    $seller->setRegistrationName('Maximum Speed Tech Supply LTD')
        ->setVatNumber('399999999900003')
        ->setPartyIdentification('1010010000')
        ->setPartyIdentificationId('CRN')
        ->setStreetName('Prince Sultan')
        ->setBuildingNumber('2322')
        ->setCitySubdivisionName('Al-Murabba')
        ->setCityName('Riyadh')
        ->setPostalZone('23333')
        ->setCountryCode('SA');

    $buyer = new BuyerData();
    $buyer->setRegistrationName('Fatoora Samples LTD')
        ->setVatNumber('399999999800003')
        ->setStreetName('Salah Al-Din')
        ->setBuildingNumber('1111')
        ->setCitySubdivisionName('Al-Murooj')
        ->setCityName('Riyadh')
        ->setPostalZone('12222')
        ->setCountryCode('SA');

    // Example 1: Standard Tax Invoice (B2B) - Requires Clearance
    echo "=== Standard Tax Invoice (B2B) - Requires Clearance ===\n";
    $standardInvoice = new InvoiceData();
    $standardInvoice->setInvoiceNumber('INV-001')
        ->standard() // Standard Invoice
        ->taxInvoice() // Tax Invoice (388)
        ->setIssueDate($today)
        ->setIssueTime($now)
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
    $original = submit($zatcaManager, $standardInvoice, $counter, $previousHash);

    // Example 2: Simplified Tax Invoice (B2C) - No Clearance Required
    //
    // For a B2C invoice to an unidentified walk-in customer, don't call
    // setBuyer(). The buyer party then contains only the country (SA), the
    // smallest form UBL and ZATCA accept.
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
        ->setIssueDate($today)
        ->setIssueTime($now)
        ->setSeller($seller);
        // No setBuyer(): walk-in customer.

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
    submit($zatcaManager, $simplifiedInvoice, $counter, $previousHash);

    // Example 3: Debit Note - Requires Clearance if Standard
    echo "=== Standard Debit Note - Requires Clearance ===\n";
    $debitNote = new InvoiceData();
    $debitNote->setInvoiceNumber('DN-001')
        ->standard() // or simplified() to correct a simplified invoice
        ->debitNote() // Debit Note (383)
        ->setIssueDate($today)
        ->setIssueTime($now)
        ->addBillingReference(['id' => 'INV-001']) // the original invoice (BR-KSA-56)
        ->addPaymentMeans([
            'code' => '10',
            'instruction_note' => 'Additional delivery charge agreed with the customer', // reason (BR-KSA-17)
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
    submit($zatcaManager, $debitNote, $counter, $previousHash);

    // Example 4: Credit Note - Requires Clearance if Standard
    echo "=== Standard Credit Note - Requires Clearance ===\n";
    $creditNote = new InvoiceData();
    $creditNote->setInvoiceNumber('CN-001')
        ->standard() // or simplified() to correct a simplified invoice
        ->creditNote() // Credit Note (381)
        ->setIssueDate($today)
        ->setIssueTime($now)
        ->addBillingReference(['id' => 'INV-001'])
        ->addPaymentMeans([
            'code' => '10',
            'instruction_note' => 'Returned one damaged item', // reason (BR-KSA-17)
        ])
        ->setSeller($seller)
        ->setBuyer($buyer);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Product 1 (returned)')
        ->setQuantity(1)
        ->setUnitPrice(100.00) // positive: the credit note type makes it a credit
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $creditNote->addLine($line);
    submit($zatcaManager, $creditNote, $counter, $previousHash);

    // Example 5: Prepayment Invoice (386)
    echo "=== Prepayment Invoice ===\n";
    $prepaymentInvoice = new InvoiceData();
    $prepaymentInvoice->setInvoiceNumber('PP-001')
        ->standard()
        ->prepaymentInvoice() // Prepayment Invoice (386)
        ->setIssueDate($today)
        ->setIssueTime($now)
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
    submit($zatcaManager, $prepaymentInvoice, $counter, $previousHash);

    // Example 6: Final invoice deducting the prepayment
    //
    // The prepayment becomes an extra zero-value line referencing PP-001;
    // PrepaidAmount = 500.00 + 75.00 and PayableAmount is reduced by it.
    echo "=== Final Invoice with Prepayment Deduction ===\n";
    $finalInvoice = new InvoiceData();
    $finalInvoice->setInvoiceNumber('INV-003')
        ->standard()
        ->taxInvoice()
        ->setIssueDate($today)
        ->setIssueTime($now)
        ->setSeller($seller)
        ->setBuyer($buyer)
        ->addPrepayment([
            'id'             => 'PP-001',
            'issue_date'     => $today,
            'issue_time'     => $now,
            'taxable_amount' => 500.00,
        ]);

    $line = new InvoiceLineData();
    $line->setId(1)
        ->setItemName('Project delivery')
        ->setQuantity(1)
        ->setUnitPrice(2000.00)
        ->setTaxPercent(15.0)
        ->calculateTotals();

    $finalInvoice->addLine($line);
    submit($zatcaManager, $finalInvoice, $counter, $previousHash);
    echo "Amount due after prepayment: " . number_format($finalInvoice->getPayableAmount(), 2) . " SAR\n\n"; // 2300.00 - 575.00

    echo "All invoice types processed successfully!\n";

} catch (ZatcaException $e) {
    echo "ZATCA Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

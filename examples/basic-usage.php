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
        ->setPartyIdentification('1010203030')
        ->setPartyIdentificationId('CRN') // Commercial Registration Number
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
    $qrRaw       = isset($result['qr_code']) ? (string)$result['qr_code'] : '';
    $invoiceHash = $result['invoice_hash'] ?? '';
    $uuid        = $result['uuid'] ?? '';
    $isClearance = !empty($result['is_clearance_required']);
    $responsePretty = json_encode($result['response'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $xmlSavedPath = 'signed-invoice.xml';

    echo '<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#0e1430;color:#eef3ff;margin:16px}
  .card{max-width:900px;margin:auto;background:#121a33;border:1px solid #1f2a4d;border-radius:12px;overflow:hidden}
  .head{padding:14px 16px;background:#0b1026;border-bottom:1px solid #1f2a4d;font-weight:700}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:10px 12px;border-top:1px solid #1f2a4d;font-size:14px;vertical-align:top}
  .tbl th{width:220px;color:#cfe0ff;text-align:left;background:#0f1738}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1f2a4d;background:#1b2547;color:#cfe0ff;font-family:monospace}
  .ok{color:#17c964;font-weight:700}
  .warn{color:#f5a524;font-weight:700}
  .btn{display:inline-block;margin:12px 0 0 16px;padding:8px 12px;border:1px solid #1f2a4d;border-radius:8px;background:#0b1430;color:#fff;text-decoration:none}
  pre{margin:0;padding:12px 16px;background:#0a0f23;border-top:1px solid #1f2a4d;color:#d9e3ff;overflow:auto}
</style>';

echo '<div class="card">';
echo '  <div class="head">Invoice processed successfully</div>';
echo '  <table class="tbl">';
echo '    <tr><th width="30%">Status</th><td><span class="ok">Success</span></td></tr>';
echo '    <tr><th width="30%">Clearance Required</th><td><span class="'.($isClearance?'warn':'ok').'">'.($isClearance?'Yes':'No').'</span></td></tr>';
echo '    <tr><th width="30%">UUID</th><td><span class="pill">'.esc($uuid).'</span></td></tr>';
echo '    <tr><th width="30%">Invoice Hash</th><td><span class="pill">'.esc($invoiceHash).'</span></td></tr>';
echo '    <tr><th width="30%">QR (raw)</th><td><span class="pill">'.esc(trunc($qrRaw, 140)).'</span></td></tr>';
echo '    <tr><th width="30%">Signed XML</th><td><a class="btn" href="'.esc($xmlSavedPath).'" download>⬇️ Download signed-invoice.xml</a></td></tr>';
echo '  </table>';

echo '  <div class="head" style="border-top:1px solid #1f2a4d;">ZATCA API Response</div>';
echo '  <pre>'.esc($responsePretty).'</pre>';
echo '</div>';

} catch (ZatcaException $e) {
    echo "ZATCA Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
} 



function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function trunc($v,$len=120){ $v=(string)$v; return mb_strlen($v)>$len ? mb_substr($v,0,$len).'…' : $v; }

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
        ->simplified() // Standard Invoice (requires clearance)
        ->taxInvoice() // Tax Invoice (388)
        ->setIssueDate(date('Y-m-d'))
        ->setIssueTime(date('H:i:s'))
        ->setDueDate(date('Y-m-d'))
        ->setCurrencyCode('SAR')
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
    $status = $result['response']['validationResults']['status'] ?? '';
    $reportingStatus = $result['response']['reportingStatus'] ?? '';
    $clearanceStatus = $result['response']['clearanceStatus'] ?? '';
    $qrRaw = isset($result['qr_code']) ? (string)$result['qr_code'] : '';
    $invoiceHash = $result['invoice_hash'] ?? '';
    $uuid = $result['uuid'] ?? '';
    $isClearance = !empty($result['is_clearance_required']);
    
    // Extract validation messages
    $infoMessages = $result['response']['validationResults']['infoMessages'] ?? [];
    $warningMessages = $result['response']['validationResults']['warningMessages'] ?? [];
    $errorMessages = $result['response']['validationResults']['errorMessages'] ?? [];
    
    $xmlSavedPath = 'signed-invoice.xml';
    file_put_contents($xmlSavedPath, $result['xml']);

    $isSuccess = ($status === 'PASS' || $status === 'WARNING');
    $statusColor = match($status) {
        'PASS' => '#16a34a',
        'WARNING' => '#ca8a04', 
        'ERROR' => '#dc2626',
        default => '#6b7280'
    };

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZATCA Invoice Result</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
            padding: 24px;
            color: #1f2937;
        }
        .container { max-width: 960px; margin: 0 auto; }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px 16px 0 0;
            padding: 24px 28px;
            color: white;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .header-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header p { font-size: 14px; opacity: 0.9; margin-top: 4px; }
        
        /* Card */
        .card {
            background: #ffffff;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        /* Status Banner */
        .status-banner {
            padding: 16px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        .status-pass { background: #dcfce7; color: #16a34a; }
        .status-warning { background: #fef3c7; color: #ca8a04; }
        .status-error { background: #fee2e2; color: #dc2626; }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1px;
            background: #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-item {
            background: #fff;
            padding: 16px 28px;
        }
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .info-value {
            font-family: "SF Mono", Monaco, "Cascadia Code", monospace;
            font-size: 13px;
            color: #374151;
            word-break: break-all;
            line-height: 1.5;
        }
        .info-value.highlight {
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        /* Messages Section */
        .messages-section {
            padding: 20px 28px;
            border-bottom: 1px solid #e5e7eb;
        }
        .messages-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #374151;
        }
        .message-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .message-item:last-child { margin-bottom: 0; }
        .message-info { background: #eff6ff; border-left: 3px solid #3b82f6; }
        .message-warning { background: #fffbeb; border-left: 3px solid #f59e0b; }
        .message-error { background: #fef2f2; border-left: 3px solid #ef4444; }
        .message-code { 
            font-family: monospace;
            font-size: 11px;
            background: rgba(0,0,0,0.06);
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }
        
        /* Actions */
        .actions {
            padding: 20px 28px;
            background: #f9fafb;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,0.4); }
        .btn-secondary {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-secondary:hover { background: #f9fafb; }
        
        /* Response Section */
        .response-section {
            border-top: 1px solid #e5e7eb;
        }
        .response-header {
            padding: 16px 28px;
            background: #f9fafb;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .response-header:hover { background: #f3f4f6; }
        .response-body {
            padding: 0;
            background: #1e293b;
            max-height: 400px;
            overflow: auto;
        }
        .response-body pre {
            margin: 0;
            padding: 20px 28px;
            font-family: "SF Mono", Monaco, "Cascadia Code", monospace;
            font-size: 12px;
            line-height: 1.6;
            color: #e2e8f0;
        }
        
        /* Utility */
        .truncate { 
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="container">';

    // Header
    echo '<div class="header">
            <div class="header-icon">' . ($isSuccess ? '✓' : '✕') . '</div>
            <div>
                <h1>' . ($isSuccess ? 'Invoice Processed Successfully' : 'Invoice Processing Failed') . '</h1>
                <p>ZATCA E-Invoice Validation Result</p>
            </div>
          </div>';

    echo '<div class="card">';

    // Status Banner
    $statusClass = match($status) {
        'PASS' => 'status-pass',
        'WARNING' => 'status-warning',
        default => 'status-error'
    };
    echo '<div class="status-banner">
            <span class="status-badge ' . $statusClass . '">● ' . esc($status) . '</span>';
    if ($reportingStatus) {
        echo '<span class="status-badge" style="background:#f3f4f6;color:#6b7280;">Reporting: ' . esc($reportingStatus) . '</span>';
    }
    if ($clearanceStatus) {
        echo '<span class="status-badge" style="background:#f3f4f6;color:#6b7280;">Clearance: ' . esc($clearanceStatus) . '</span>';
    }
    echo '</div>';

    // Info Grid
    echo '<div class="info-grid">
            <div class="info-item">
                <div class="info-label">Invoice UUID</div>
                <div class="info-value highlight">' . esc($uuid) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Clearance Required</div>
                <div class="info-value"><span style="color:' . ($isClearance ? '#ca8a04' : '#16a34a') . ';font-weight:600;">' . ($isClearance ? 'Yes' : 'No') . '</span></div>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <div class="info-label">Invoice Hash</div>
                <div class="info-value highlight">' . esc($invoiceHash) . '</div>
            </div>
            <div class="info-item" style="grid-column: 1 / -1;">
                <div class="info-label">QR Code Data</div>
                <div class="info-value highlight" style="max-height:80px;overflow:auto;">' . esc($qrRaw) . '</div>
            </div>
          </div>';

    // Validation Messages
    if (!empty($infoMessages) || !empty($warningMessages) || !empty($errorMessages)) {
        echo '<div class="messages-section">';
        echo '<div class="messages-title">Validation Messages</div>';
        
        foreach ($errorMessages as $msg) {
            echo '<div class="message-item message-error">
                    <span>❌</span>
                    <div><span class="message-code">' . esc($msg['code'] ?? '') . '</span> ' . esc($msg['message'] ?? '') . '</div>
                  </div>';
        }
        foreach ($warningMessages as $msg) {
            echo '<div class="message-item message-warning">
                    <span>⚠️</span>
                    <div><span class="message-code">' . esc($msg['code'] ?? '') . '</span> ' . esc($msg['message'] ?? '') . '</div>
                  </div>';
        }
        foreach ($infoMessages as $msg) {
            echo '<div class="message-item message-info">
                    <span>ℹ️</span>
                    <div><span class="message-code">' . esc($msg['code'] ?? '') . '</span> ' . esc($msg['message'] ?? '') . '</div>
                  </div>';
        }
        echo '</div>';
    }

    // Actions
    echo '<div class="actions">
            <a href="' . esc($xmlSavedPath) . '" download class="btn btn-primary">
                <span>📄</span> Download Signed XML
            </a>
            <button onclick="document.getElementById(\'response-body\').style.display = document.getElementById(\'response-body\').style.display === \'none\' ? \'block\' : \'none\';" class="btn btn-secondary">
                <span>{ }</span> Toggle Raw Response
            </button>
          </div>';

    // Raw Response
    $responsePretty = json_encode($result['response'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo '<div class="response-section">
            <div class="response-body" id="response-body" style="display:none;">
                <pre>' . esc($responsePretty) . '</pre>
            </div>
          </div>';

    echo '</div>'; // card
    echo '</div>'; // container
    echo '</body></html>';

} catch (ZatcaException $e) {
    echo '<!DOCTYPE html><html><head><style>body{font-family:sans-serif;padding:40px;background:#fef2f2;}.error{background:#fff;padding:24px;border-radius:12px;border-left:4px solid #ef4444;max-width:600px;margin:auto;}</style></head><body>';
    echo '<div class="error"><h2 style="color:#dc2626;margin-bottom:12px;">ZATCA Error</h2>';
    echo '<p style="margin-bottom:8px;">' . esc($e->getMessage()) . '</p>';
    echo '<pre style="background:#f3f4f6;padding:12px;border-radius:8px;font-size:12px;overflow:auto;">' . esc(json_encode($e->getContext(), JSON_PRETTY_PRINT)) . '</pre>';
    echo '</div></body></html>';
} catch (Exception $e) {
    echo '<!DOCTYPE html><html><head><style>body{font-family:sans-serif;padding:40px;background:#fef2f2;}.error{background:#fff;padding:24px;border-radius:12px;border-left:4px solid #ef4444;max-width:600px;margin:auto;}</style></head><body>';
    echo '<div class="error"><h2 style="color:#dc2626;margin-bottom:12px;">General Error</h2>';
    echo '<p>' . esc($e->getMessage()) . '</p>';
    echo '</div></body></html>';
}

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

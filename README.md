# ZATCA PHP Package

[![Packagist Version](https://img.shields.io/packagist/v/khaledhajsalem/zatca-php)](https://packagist.org/packages/khaledhajsalem/zatca-php)
[![Downloads](https://img.shields.io/packagist/dt/khaledhajsalem/zatca-php)](https://packagist.org/packages/khaledhajsalem/zatca-php)
[![License](https://img.shields.io/packagist/l/khaledhajsalem/zatca-php)](https://packagist.org/packages/khaledhajsalem/zatca-php)
[![ZATCA Phase 2](https://img.shields.io/badge/ZATCA-Phase%202%20Compliant-success)](https://zatca.gov.sa)

A PHP package for ZATCA (Saudi Arabia) Phase 2 e-invoicing. Handles XML generation (UBL 2.1), digital signing, QR codes, and API submission — all without database dependencies.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Key Concepts](#key-concepts)
- [How It Works (Lifecycle)](#how-it-works-lifecycle)
- [Step 1: Generate CSR & Private Key](#step-1-generate-csr--private-key)
- [Step 2: Get Your Certificate from ZATCA](#step-2-get-your-certificate-from-zatca)
- [Step 3: Create & Submit an Invoice](#step-3-create--submit-an-invoice)
- [Invoice Types](#invoice-types)
- [Credit & Debit Notes](#credit--debit-notes)
- [Data Reference](#data-reference)
- [Error Handling](#error-handling)
- [Package Structure](#package-structure)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Requirements

- PHP 8.0+
- OpenSSL extension
- Composer

## Installation

```bash
composer require khaledhajsalem/zatca-php
```

---

## Key Concepts

Before using this package, understand these ZATCA-specific terms:

| Term | What it means |
|------|--------------|
| **Standard Invoice** | B2B/B2G invoice. Must be **cleared** by ZATCA before you can send it to the buyer. Type name: `0100000`. |
| **Simplified Invoice** | B2C invoice (e.g., retail receipt). Must be **reported** to ZATCA within 24 hours. Type name: `0200000`. |
| **Clearance** | ZATCA validates and approves a Standard invoice in real-time. You get back a "cleared" XML. |
| **Reporting** | You send a Simplified invoice to ZATCA for record-keeping. No real-time approval needed. |
| **PIH (Previous Invoice Hash)** | SHA-256 hash of the previous invoice. Creates a tamper-proof chain. First invoice uses `'MA=='` (base64 encoded '0'). |
| **ICV (Invoice Counter Value)** | Sequential counter starting at `1`. Must increment for every invoice. |
| **CSR** | Certificate Signing Request — you generate this and send it to ZATCA to get your signing certificate. |
| **OTP** | One-Time Password — ZATCA gives you this when you register your device on the Fatoora portal. |

### Invoice Type Codes

| Code | Type | Method |
|------|------|--------|
| `388` | Tax Invoice | `->taxInvoice()` |
| `381` | Credit Note | `->creditNote()` |
| `383` | Debit Note | `->debitNote()` |
| `386` | Prepayment Invoice | `->prepaymentInvoice()` |

### Party Identification Schemes

Both seller and buyer support these identification types:

| Scheme ID | Description |
|-----------|-------------|
| `CRN` | Commercial Registration Number |
| `VAT` | VAT Number |
| `TIN` | Tax Identification Number |
| `NAT` | National ID |
| `IQA` | Iqama Number |
| `GCC` | GCC ID |
| `PAS` | Passport ID |
| `MOM` | MOMRAH License |
| `MLS` | MHRSD License |
| `SAG` | MISA License |
| `700` | 700 Number |
| `OTH` | Other ID |

---

## How It Works (Lifecycle)

Here is the complete flow from setup to invoice submission:

```
┌─────────────────────────────────────────────────────────────────┐
│  ONE-TIME SETUP                                                 │
│                                                                 │
│  1. Generate CSR + Private Key  (CertificateBuilder)            │
│  2. Submit CSR to ZATCA with OTP → get Compliance Certificate   │
│  3. Run compliance tests with the compliance certificate        │
│  4. Request Production Certificate → get Production Certificate │
│                                                                 │
│  Save: certificate.pem, private.pem, secret key                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  FOR EVERY INVOICE                                              │
│                                                                 │
│  1. Create InvoiceData (number, date, type, PIH, ICV)           │
│  2. Create SellerData  (company name, VAT, address)             │
│  3. Create BuyerData   (customer name, VAT, address)            │
│  4. Create InvoiceLineData items (name, qty, price, tax%)       │
│  5. Call calculateTotals() on lines, then on invoice            │
│  6. Submit via ZatcaManager->processInvoice()                   │
│     → Standard invoice? ZATCA clears it (real-time)             │
│     → Simplified invoice? ZATCA reports it                      │
│  7. Save the invoice_hash as PIH for the next invoice           │
└─────────────────────────────────────────────────────────────────┘
```

---

## Step 1: Generate CSR & Private Key

> **Do this once** when setting up your system with ZATCA.

```php
use KhaledHajSalem\Zatca\Support\CertificateBuilder;

$builder = new CertificateBuilder();
$builder->setOrganizationIdentifier('300000000000003') // 15 digits, starts & ends with 3
    ->setSerialNumber('MySolution', 'Model1', 'SN001')
    ->setCommonName('Your Company Name')
    ->setCountryName('SA')
    ->setOrganizationName('Your Company Name')
    ->setOrganizationalUnitName('IT Department')
    ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
    ->setInvoiceType(1100) // 4 digits: [Standard][Simplified][future][future] — 1100 = both standard & simplified
    ->setProduction(false) // false = sandbox/simulation, true = production
    ->setBusinessCategory('Legal Entity');

$builder->generateAndSave('storage/certificate.csr', 'storage/private.pem');
```

**What happens next:**
1. Log in to the [ZATCA Fatoora Portal](https://fatoora.zatca.gov.sa/)
2. Register your device — ZATCA gives you an **OTP**
3. Use the OTP to request a compliance certificate (see Step 2)

---

## Step 2: Get Your Certificate from ZATCA

```php
use KhaledHajSalem\Zatca\Services\ZatcaAPIService;

// Initialize API service
$apiService = new ZatcaAPIService('sandbox'); // 'sandbox', 'simulation', or 'production'

// ── Step 2a: Request Compliance Certificate ──
$csr = file_get_contents('storage/certificate.csr');
$otp = '123456'; // OTP from ZATCA Fatoora Portal

$complianceResult = $apiService->requestComplianceCertificate($csr, $otp);

// Save the compliance certificate
file_put_contents('storage/certificate.pem', $complianceResult->getCertificate());
$complianceSecret    = $complianceResult->getSecret();     // Save this — it's your API secret
$complianceRequestId = $complianceResult->getRequestId();  // Need this for production certificate

// ── Step 2b: Run Compliance Tests ──
// Submit test invoices using the compliance certificate (see Step 3)
// Once all tests pass...

// ── Step 2c: Request Production Certificate ──
$complianceCert = file_get_contents('storage/certificate.pem');

$productionResult = $apiService->requestProductionCertificate(
    $complianceCert,          // Compliance certificate
    $complianceSecret,        // Compliance secret
    $complianceRequestId      // Request ID from step 2a
);

// Save the production certificate — use this for all real invoices
file_put_contents('storage/certificate.pem', $productionResult->getCertificate());
$productionSecret = $productionResult->getSecret(); // Save this as your new API secret
```

---

## Step 3: Create & Submit an Invoice

This is the main workflow you'll use for every invoice.

### Simplified Tax Invoice (B2C)

```php
use KhaledHajSalem\Zatca\ZatcaManager;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;

// ── 1. Initialize ZatcaManager ──
$zatcaManager = new ZatcaManager([
    'environment'      => 'sandbox',                              // 'sandbox', 'simulation', or 'production'
    'certificate_path' => __DIR__ . '/storage/certificate.pem',   // Path to your certificate file
    'private_key_path' => __DIR__ . '/storage/private.pem',       // Path to your private key file
    'secret'           => 'CkYsEXfV8c1gFHAtFWoZv73pGMvh/Qyo4LzKM2h/8Hg=', // API secret from ZATCA
]);

// ── 2. Create Invoice Data ──
$invoiceData = new InvoiceData();
$invoiceData
    ->setInvoiceNumber('INV-001')           // Your invoice number
    ->simplified()                          // B2C invoice (reporting) — or ->standard() for B2B (clearance)
    ->taxInvoice()                          // Tax Invoice (388) — or ->creditNote(), ->debitNote(), ->prepaymentInvoice()
    ->setIssueDate('2025-01-15')            // Issue date in Y-m-d format
    ->setIssueTime('10:30:00')              // Issue time in H:i:s format
    ->setDueDate('2025-02-15')              // Due date in Y-m-d format
    ->setCurrencyCode('SAR')                // Currency code (ISO 4217)
    ->setDocumentCurrencyCode('SAR')        // Document currency (usually same as above)
    ->setTaxCurrencyCode('SAR')             // Tax currency (usually same as above)
    ->setInvoiceCounter('1')                // ICV: sequential counter, must increment per invoice
    ->setPreviousInvoiceHash('MA==');        // PIH: 'MA==' for first invoice, then use hash from previous invoice

// ── 3. Create Seller Data ──
$seller = new SellerData();
$seller->setRegistrationName('Your Company Name')  // Company legal name
    ->setVatNumber('399999999900003')               // VAT number (15 digits)
    ->setPartyIdentification('1010203020')          // Identification value (e.g., CRN number)
    ->setPartyIdentificationId('CRN')               // Identification type — see "Party Identification Schemes" above
    ->setStreetName('Main Street')                  // Street name
    ->setBuildingNumber('1234')                     // Building number
    ->setCityName('Riyadh')                         // City
    ->setPostalZone('12345')                        // Postal/ZIP code
    ->setCountryCode('SA')                          // 2-letter country code
    ->setPlotIdentification('PLOT-001')             // Plot identification (optional)
    ->setCitySubdivisionName('District 1');         // District name (optional)

$invoiceData->setSeller($seller);

// ── 4. Create Buyer Data ──
$buyer = new BuyerData();
$buyer->setRegistrationName('Customer Company')
    ->setVatNumber('300000000000003')               // VAT number (optional for simplified invoices)
    ->setPartyIdentification('1010203030')
    ->setPartyIdentificationId('CRN')
    ->setStreetName('Customer Street')
    ->setBuildingNumber('4567')
    ->setCityName('Jeddah')
    ->setPostalZone('54321')
    ->setCountryCode('SA');

$invoiceData->setBuyer($buyer);

// ── 5. Add Line Items ──
$line1 = new InvoiceLineData();
$line1->setId(1)                                    // Line number (sequential)
    ->setItemName('Product 1')                      // Item name
    ->setDescription('High-quality product')        // Description (optional)
    ->setQuantity(2)                                // Quantity
    ->setUnitPrice(100.00)                          // Unit price (tax-exclusive)
    ->setTaxPercent(15.0)                           // VAT percentage
    ->calculateTotals();                            // Auto-calculates: lineExtension, taxAmount, taxExclusive, taxInclusive

$line2 = new InvoiceLineData();
$line2->setId(2)
    ->setItemName('Product 2')
    ->setQuantity(1)
    ->setUnitPrice(50.00)
    ->setTaxPercent(15.0)
    ->calculateTotals();

$invoiceData->addLine($line1);
$invoiceData->addLine($line2);

// ── 6. Calculate Invoice Totals ──
$invoiceData->calculateTotals();                    // Sums all line items into invoice-level totals

// ── 7. Submit to ZATCA ──
$result = $zatcaManager->processInvoice($invoiceData);

// ── 8. Use the Result ──
echo $result['uuid'];                               // Invoice UUID (generated automatically)
echo $result['invoice_hash'];                       // Invoice hash — SAVE THIS as PIH for your next invoice
echo $result['qr_code'];                            // Base64-encoded QR code
echo $result['xml'];                                // Signed XML string
echo $result['is_clearance_required'];              // true for standard, false for simplified

// API response from ZATCA:
echo $result['response']['validationResults']['status'];  // 'PASS', 'WARNING', or 'ERROR'
echo $result['response']['reportingStatus'];              // For simplified: 'REPORTED'
echo $result['response']['clearanceStatus'];              // For standard: 'CLEARED'
```

### Standard Tax Invoice (B2B)

The only difference from simplified is the invoice type — everything else is the same:

```php
$invoiceData = new InvoiceData();
$invoiceData
    ->setInvoiceNumber('INV-002')
    ->standard()                                    // ← This is the only change (B2B, requires clearance)
    ->taxInvoice()
    ->setIssueDate('2025-01-15')
    ->setIssueTime('10:30:00')
    ->setCurrencyCode('SAR')
    ->setDocumentCurrencyCode('SAR')
    ->setTaxCurrencyCode('SAR')
    ->setInvoiceCounter('2')                        // ICV: second invoice
    ->setPreviousInvoiceHash($previousInvoiceHash); // PIH: hash from INV-001

// ... seller, buyer, lines same as above ...

$result = $zatcaManager->processInvoice($invoiceData);

// For standard invoices, ZATCA returns a cleared XML:
if ($result['response']['clearanceStatus'] === 'CLEARED') {
    $clearedXml = $result['xml']; // Use this XML (not your original)
}
```

---

## Invoice Types

### Summary

| Type | Code | Name | Clearance? | Method |
|------|------|------|-----------|--------|
| Standard Tax Invoice | `388` | `0100000` | Yes (real-time) | `->standard()->taxInvoice()` |
| Simplified Tax Invoice | `388` | `0200000` | No (report within 24h) | `->simplified()->taxInvoice()` |
| Standard Credit Note | `381` | `0100000` | Yes | `->standard()->creditNote()` |
| Simplified Credit Note | `381` | `0200000` | No | `->simplified()->creditNote()` |
| Standard Debit Note | `383` | `0100000` | Yes | `->standard()->debitNote()` |
| Simplified Debit Note | `383` | `0200000` | No | `->simplified()->debitNote()` |
| Prepayment Invoice | `386` | — | Depends on standard/simplified | `->prepaymentInvoice()` |

---

## Credit & Debit Notes

Credit and debit notes **must reference the original invoice** using `addBillingReference()`. Payment means are optional but recommended.

```php
// ── Credit Note (returns/refunds) ──
$creditNote = new InvoiceData();
$creditNote
    ->setInvoiceNumber('CN-001')
    ->simplified()                                  // or ->standard()
    ->creditNote()                                  // Type code 381
    ->setIssueDate('2025-01-20')
    ->setIssueTime('14:00:00')
    ->setCurrencyCode('SAR')
    ->setDocumentCurrencyCode('SAR')
    ->setTaxCurrencyCode('SAR')
    ->setInvoiceCounter('3')
    ->setPreviousInvoiceHash($previousHash)

    // REQUIRED: Reference to the original invoice
    ->addBillingReference([
        'id'   => 'INV-001',                        // Original invoice number
        'uuid' => '63decc4e-cc4d-4e3b-878c-b772560bb5f1', // Original invoice UUID
    ])

    // OPTIONAL: Payment means (reason for the note)
    ->addPaymentMeans([
        'code'             => '10',                  // Payment method code
        'instruction_note' => 'Returns',             // Reason: Returns, Correction, Cancellation, etc.
    ]);

// ... set seller, buyer, lines, calculateTotals(), then submit
$result = $zatcaManager->processInvoice($creditNote);
```

```php
// ── Debit Note (additional charges) ──
$debitNote = new InvoiceData();
$debitNote
    ->setInvoiceNumber('DN-001')
    ->standard()                                    // or ->simplified()
    ->debitNote()                                   // Type code 383
    ->setIssueDate('2025-01-20')
    ->setIssueTime('14:00:00')
    ->setCurrencyCode('SAR')
    ->setDocumentCurrencyCode('SAR')
    ->setTaxCurrencyCode('SAR')
    ->setInvoiceCounter('4')
    ->setPreviousInvoiceHash($previousHash)
    ->addBillingReference([
        'id'   => 'INV-001',
        'uuid' => '63decc4e-cc4d-4e3b-878c-b772560bb5f1',
    ])
    ->addPaymentMeans([
        'code'             => '10',
        'instruction_note' => 'Addition',
    ]);

// ... set seller, buyer, lines, calculateTotals(), then submit
$result = $zatcaManager->processInvoice($debitNote);
```

---

## Data Reference

### InvoiceData — All Setters

| Method | Type | Required | Description |
|--------|------|----------|-------------|
| `setInvoiceNumber($num)` | `string` | Yes | Your invoice number |
| `standard()` | — | Yes* | Set as Standard (B2B). *One of standard/simplified required |
| `simplified()` | — | Yes* | Set as Simplified (B2C) |
| `taxInvoice()` | — | Yes* | Tax Invoice (388). *One of tax/credit/debit/prepayment required |
| `creditNote()` | — | — | Credit Note (381) |
| `debitNote()` | — | — | Debit Note (383) |
| `prepaymentInvoice()` | — | — | Prepayment (386) |
| `setIssueDate($date)` | `string` | Yes | Format: `Y-m-d` |
| `setIssueTime($time)` | `string` | Yes | Format: `H:i:s` |
| `setDueDate($date)` | `string` | No | Format: `Y-m-d` |
| `setCurrencyCode($code)` | `string` | Yes | ISO 4217 (e.g., `SAR`) |
| `setDocumentCurrencyCode($code)` | `string` | Yes | Usually same as currency code |
| `setTaxCurrencyCode($code)` | `string` | Yes | Usually same as currency code |
| `setInvoiceCounter($icv)` | `string` | Yes | Sequential counter starting at `1` |
| `setPreviousInvoiceHash($pih)` | `string` | Yes | `'MA=='` for first invoice |
| `setSeller($seller)` | `SellerData` | Yes | Seller information |
| `setBuyer($buyer)` | `BuyerData` | Yes | Buyer information |
| `addLine($line)` | `InvoiceLineData` | Yes | At least one line required |
| `calculateTotals()` | — | Yes | Call after adding all lines |
| `addBillingReference($ref)` | `array` | For CN/DN | Keys: `id`, `uuid` |
| `addPaymentMeans($pm)` | `array` | No | Keys: `code`, `instruction_note` |
| `addAllowance($allowance)` | `array` | No | Document-level discount |
| `addCharge($charge)` | `array` | No | Document-level charge |

### SellerData — All Setters

| Method | Type | Required | Description |
|--------|------|----------|-------------|
| `setRegistrationName($name)` | `string` | Yes | Company legal name |
| `setVatNumber($vat)` | `string` | Yes | 15-digit VAT number |
| `setPartyIdentification($value)` | `string` | Yes | ID value (e.g., CRN number) |
| `setPartyIdentificationId($scheme)` | `string` | Yes | Scheme: `CRN`, `VAT`, `TIN`, etc. |
| `setStreetName($street)` | `string` | Yes | Street name |
| `setBuildingNumber($num)` | `string` | Yes | Building number |
| `setCityName($city)` | `string` | Yes | City name |
| `setPostalZone($zip)` | `string` | Yes | Postal/ZIP code |
| `setCountryCode($code)` | `string` | Yes | 2-letter code (e.g., `SA`) |
| `setPlotIdentification($plot)` | `string` | No | Plot identification |
| `setCitySubdivisionName($district)` | `string` | No | District/subdivision name |

### BuyerData — All Setters

Same methods as SellerData. For simplified invoices, `setVatNumber()` is optional.

### InvoiceLineData — All Setters

| Method | Type | Required | Description |
|--------|------|----------|-------------|
| `setId($id)` | `int` | Yes | Line number (sequential: 1, 2, 3...) |
| `setItemName($name)` | `string` | Yes | Item name |
| `setDescription($desc)` | `string` | No | Item description |
| `setQuantity($qty)` | `float` | Yes | Quantity |
| `setUnitPrice($price)` | `float` | Yes | Unit price (tax-exclusive) |
| `setTaxPercent($pct)` | `float` | Yes | VAT percentage (e.g., `15.0`) |
| `setUnitCode($code)` | `string` | No | Unit code (default: `EA`) |
| `setItemCode($code)` | `string` | No | Item code |
| `calculateTotals()` | — | Yes | Auto-calculates all amounts from qty × price × tax% |
| `setAllowanceAmount($amt)` | `float` | No | Line-level discount (set before calculateTotals) |
| `setChargeAmount($amt)` | `float` | No | Line-level charge (set before calculateTotals) |

> **Tip:** Call `calculateTotals()` on each line item, then call `calculateTotals()` on the invoice. This auto-fills `lineExtensionAmount`, `taxAmount`, `taxExclusiveAmount`, `taxInclusiveAmount`, and all invoice-level totals.

### Previous Invoice Hash (PIH) Chain

Every invoice references the hash of the previous invoice to create a tamper-proof chain:

```php
// Invoice 1 (first invoice — no previous)
$invoice1->setPreviousInvoiceHash('MA==');           // base64('0')
$result1 = $zatcaManager->processInvoice($invoice1);
$hash1 = $result1['invoice_hash'];                   // Save this!

// Invoice 2 (references invoice 1)
$invoice2->setPreviousInvoiceHash($hash1);
$result2 = $zatcaManager->processInvoice($invoice2);
$hash2 = $result2['invoice_hash'];                   // Save this!

// Invoice 3 (references invoice 2)
$invoice3->setPreviousInvoiceHash($hash2);
// ... and so on
```

---

## Error Handling

```php
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;
use KhaledHajSalem\Zatca\Exceptions\CertificateBuilderException;
use KhaledHajSalem\Zatca\Exceptions\ZatcaApiException;

try {
    $result = $zatcaManager->processInvoice($invoiceData);
} catch (CertificateBuilderException $e) {
    // Certificate generation errors
    echo "Certificate error: " . $e->getMessage();
    echo "Details: " . json_encode($e->getContext());
} catch (ZatcaApiException $e) {
    // ZATCA API errors (network, validation, auth)
    echo "API error: " . $e->getMessage();
    echo "Details: " . json_encode($e->getContext());
} catch (ZatcaException $e) {
    // General package errors (missing config, file not found, etc.)
    echo "Error: " . $e->getMessage();
    echo "Details: " . json_encode($e->getContext());
}
```

All exceptions extend `ZatcaException` and provide a `getContext()` method with structured error details.

---

## Package Structure

```
zatca-php/
├── src/
│   ├── Data/                          # Data transfer objects
│   │   ├── InvoiceData.php            # Invoice header, totals, references
│   │   ├── SellerData.php             # Seller name, VAT, address
│   │   ├── BuyerData.php              # Buyer name, VAT, address
│   │   └── InvoiceLineData.php        # Line item: name, qty, price, tax
│   ├── Exceptions/                    # Exception classes
│   │   ├── ZatcaException.php         # Base exception (all others extend this)
│   │   ├── CertificateBuilderException.php
│   │   ├── ZatcaApiException.php
│   │   └── ZatcaStorageException.php
│   ├── Services/                      # External services
│   │   ├── ZatcaAPIService.php        # ZATCA API client (clearance, reporting, compliance)
│   │   └── Storage.php                # File storage helper
│   ├── Support/                       # Internal support classes
│   │   ├── Certificate.php            # Certificate loading & hashing
│   │   ├── CertificateBuilder.php     # CSR & private key generation
│   │   ├── InvoiceExtension.php       # UBL XML extension handling
│   │   ├── InvoiceSignatureBuilder.php # XMLDsig signature builder
│   │   ├── InvoiceSigner.php          # Signs XML, generates QR & hash
│   │   ├── QRCodeGenerator.php        # TLV-encoded QR code generation
│   │   └── QRCodeTags/               # Individual QR code tag classes
│   ├── ZatcaInvoice.php               # UBL 2.1 XML generator
│   └── ZatcaManager.php               # Main orchestrator (the class you use)
├── examples/
│   ├── basic-usage.php                # Complete working example with HTML output
│   ├── certificate-generation.php     # CSR generation example
│   └── invoice-types.php              # Standard, simplified, credit, debit, prepayment
├── docs/
│   └── API.md                         # Detailed API reference
├── tests/
│   └── ZatcaInvoiceTest.php
├── composer.json
└── README.md
```

---

## Testing

```bash
composer test              # Run tests
composer test-coverage     # Run with coverage
composer phpstan           # Static analysis
composer cs-check          # Code style check
composer cs-fix            # Fix code style
```

---

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## Acknowledgments

This package includes code and inspiration from:

- **[php-zatca-xml](https://github.com/Saleh7/php-zatca-xml)** by [Saleh7](https://github.com/Saleh7) — XML generation and ZATCA compliance logic.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- **Email:** [khaledhajsalem@hotmail.com](mailto:khaledhajsalem@hotmail.com)
- **GitHub Issues:** [Create an issue](https://github.com/khaledhajsalem/zatca-php/issues)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.
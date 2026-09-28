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
- [Validation](#validation)
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
| **PIH (Previous Invoice Hash)** | SHA-256 hash of the previous invoice. Creates a tamper-proof chain. First invoice uses `InvoiceData::FIRST_INVOICE_HASH`, the base64 SHA-256 of `'0'` (BR-KSA-26). |
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

> **Important:** The OTP, compliance certificate and production certificate must all come from the **same environment**. For `simulation`, generate the OTP from the Fatoora **Simulation** portal; sandbox/developer-portal credentials will be rejected with *"You are not authorized to use this api endpoint"*.

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

// Save the compliance certificate (keep it separate from the production one)
file_put_contents('storage/compliance_certificate.pem', $complianceResult->getCertificate());
$complianceSecret    = $complianceResult->getSecret();     // Compliance API secret (keep it for step 2c)
$complianceRequestId = $complianceResult->getRequestId();  // Need this for production certificate

// ── Step 2b: Run Compliance Tests ──
// Submit test invoices with $apiService->validateInvoiceCompliance(...) using the
// compliance certificate and secret. In simulation/production, every invoice type
// declared in the CSR (e.g. 1100 → standard + simplified invoice, credit note and
// debit note) must pass before the production certificate is issued.
// Once all tests pass...

// ── Step 2c: Request Production Certificate ──
$complianceCert = file_get_contents('storage/compliance_certificate.pem');

$productionResult = $apiService->requestProductionCertificate(
    $complianceCert,          // Compliance certificate
    $complianceSecret,        // Compliance secret
    $complianceRequestId      // Request ID from step 2a
);

// Save the production certificate — use this for all real invoices
file_put_contents('storage/certificate.pem', $productionResult->getCertificate());
file_put_contents('storage/secret.txt', $productionResult->getSecret()); // Your API secret for invoices
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
    'secret'           => trim(file_get_contents(__DIR__ . '/storage/secret.txt')), // API secret from step 2c
    'validate'         => true,                                   // Check business rules before submitting (optional)
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
    ->setPreviousInvoiceHash(InvoiceData::FIRST_INVOICE_HASH); // PIH: first invoice, then use hash from previous invoice

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
    ->setCitySubdivisionName('District 1');         // District name (required, BR-KSA-09)

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
    ->setCitySubdivisionName('District 2')          // District (required for Saudi buyers on standard invoices)
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

### Transaction Flags (KSA-2)

The `name` attribute is `NNPNESB`: the subtype (`01` standard, `02` simplified) followed by five flags. Set them with chainable methods, in any order:

| Flag | Method | Position | Allowed on simplified? |
|------|--------|----------|------------------------|
| Third party | `->thirdParty()` | P (3) | Yes |
| Nominal supply | `->nominal()` | N (4) | Yes |
| Export | `->export()` | E (5) | No |
| Summary | `->summary()` | S (6) | Yes |
| Self-billed | `->selfBilled()` | B (7) | No |

```php
$invoice->standard()->export();                                   // 0100100
$invoice->simplified()->summary()                                 // 0200010
    ->setSupplyDate('2024-01-01')->setSupplyEndDate('2024-01-31');
```

Pass `false` to clear a flag (`->export(false)`). `generateXml()` rejects the combinations ZATCA forbids:
- an export invoice that is self-billed (BR-KSA-07)
- a simplified invoice flagged as export or self-billed (BR-KSA-31)
- an export invoice with a buyer VAT number (BR-KSA-46)
- a simplified summary invoice without a supply end date or buyer name (BR-KSA-71/72)

### Supply Dates

The supply date (KSA-5) defaults to the issue date. Summary invoices also need a supply end date (KSA-24):

```php
$invoice->setSupplyDate('2024-01-01')->setSupplyEndDate('2024-01-31');
```

### Prepayment Adjustment

To deduct an earlier prepayment invoice (`386`) from a final invoice, add it with `addPrepayment()`. Each prepayment becomes a zero-value invoice line that references it (§9.5). `PrepaidAmount` becomes the sum of taxable amount and VAT, and `PayableAmount` is reduced by it:

```php
$invoice->addPrepayment([
    'id'             => 'PP-001',       // invoice number of the prepayment invoice
    'issue_date'     => '2024-01-10',
    'issue_time'     => '09:30:00',
    'taxable_amount' => 86.96,
    'tax_category'   => 'S',            // optional, default S
    'tax_percent'    => 15,             // optional, default 15 for S, 0 for Z/E/O
]);                                     // tax_amount (13.04) is calculated
```

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
| `setTaxCurrencyCode($code)` | `string` | Yes | Must be `SAR` (BR-KSA-EN16931-02) |
| `setTaxExchangeRate($rate)` | `float` | If currency ≠ SAR | 1 unit of the invoice currency in SAR, used for the SAR VAT total (BT-111) |
| `setInvoiceCounter($icv)` | `string` | Yes | Sequential counter starting at `1` |
| `setPreviousInvoiceHash($pih)` | `string` | Yes | `InvoiceData::FIRST_INVOICE_HASH` for first invoice (the default) |
| `thirdParty()` / `nominal()` / `export()` / `summary()` / `selfBilled()` | `bool` | No | KSA-2 flags. See [Transaction Flags](#transaction-flags-ksa-2) |
| `setSupplyDate($date)` | `string` | No | Supply date (KSA-5). Default: issue date |
| `setSupplyEndDate($date)` | `string` | Summary | Supply end date (KSA-24) |
| `setNote($note, $lang)` | `string` | No | Invoice note (BT-22), optional `languageID` |
| `addPrepayment($prepayment)` | `array` | No | Deduct a prepayment invoice. See [Prepayment Adjustment](#prepayment-adjustment) |
| `setPayableRoundingAmount($amt)` | `float` | No | Rounding amount (BT-114) added to the amount due |
| `getFileName()` | — | — | File name per §14, e.g. `300000000000003_20240115T103000_INV-001.xml` |
| `setSeller($seller)` | `SellerData` | Yes | Seller information |
| `setBuyer($buyer)` | `BuyerData` | Yes | Buyer information |
| `addLine($line)` | `InvoiceLineData` | Yes | At least one line required |
| `calculateTotals()` | — | Yes | Call after adding all lines |
| `addBillingReference($ref)` | `array` | For CN/DN | Keys: `id`, `uuid` |
| `addPaymentMeans($pm)` | `array` | No | Keys: `code` (10, 30, 42, 48, 1), `instruction_note`, `due_date`, `channel_code`, `payment_id` |
| `addAllowance($allowance)` | `array` | No | Document-level discount. Keys: `amount`, `reason` (default `discount`), `reason_code` (UNTDID 5189, e.g. `95`), `base_amount` + `multiplier` (percentage, both or neither; `amount` is computed if omitted), `tax_category`, `tax_percent` |
| `addCharge($charge)` | `array` | No | Document-level charge. Same keys as `addAllowance()`; `reason` and `reason_code` (UNTDID 7161, e.g. `FC` = freight) are **required** |

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
| `setCitySubdivisionName($district)` | `string` | Yes | District name (BR-KSA-09) |
| `setAdditionalStreetName($name)` | `string` | No | Additional street name |

### BuyerData — All Setters

Same methods as SellerData. For simplified invoices, `setVatNumber()` is optional.

### InvoiceLineData — All Setters

| Method | Type | Required | Description |
|--------|------|----------|-------------|
| `setId($id)` | `int` | Yes | Line number (sequential: 1, 2, 3...) |
| `setItemName($name)` | `string` | Yes | Item name |
| `setDescription($desc)` | `string` | No | Item description (`cac:Item/cbc:Description`) |
| `setQuantity($qty)` | `float` | Yes | Quantity |
| `setUnitPrice($price)` | `float` | Yes | Unit price (tax-exclusive) |
| `setTaxPercent($pct)` | `float` | Yes | VAT percentage (e.g., `15.0`). Ignored (0%) for `Z`, `E` and `O` lines |
| `setTaxCategory($cat, $reasonCode, $reasonText)` | `string` | No | VAT category: `S` (default), `Z`, `E` or `O`. See [Zero-rated, exempt & out-of-scope items](#zero-rated-exempt--out-of-scope-items) |
| `setUnitCode($code)` | `string` | No | UN/ECE Rec 20 unit code (default: `PCE`) |
| `setItemCode($code)` | `string` | No | Seller's item code (BT-155) |
| `setBuyersItemCode($code)` | `string` | No | Buyer's item code (BT-156) |
| `setStandardItemCode($code, $scheme)` | `string` | No | Standard item ID (BT-157), e.g. GTIN |
| `setPriceDiscount($amt)` | `float` | No | Per-unit discount (BT-147). `setUnitPrice()` is then the gross price; the net price is gross − discount |
| `setBaseQuantity($qty)` | `float` | No | Units the price applies to (BT-149), e.g. `12` for a price per dozen |
| `calculateTotals()` | — | Yes | Auto-calculates all amounts from qty × price × tax% |
| `setAllowanceAmount($amt)` | `float` | No | Line-level discount total (set before calculateTotals). `setUnitPrice()` is the gross, pre-discount unit price. |
| `setAllowanceReason($reason)` | `string` | No | Reason for the line discount (default: `discount`) |
| `setAllowanceReasonCode($code)` | `string` | No | UNTDID 5189 code for the line discount (e.g. `95`) |
| `setChargeAmount($amt)` | `float` | No | Line-level charge (set before calculateTotals). Requires `setChargeReason()` and `setChargeReasonCode()` |
| `setChargeReason($reason)` | `string` | With a charge | Reason for the line charge (BR-KSA-22) |
| `setChargeReasonCode($code)` | `string` | With a charge | UNTDID 7161 code (e.g. `CG` = cleaning) (BR-KSA-20) |

> **Rounding:** amounts are rounded half-up to 2 decimals at each step (line net, line VAT, VAT per category, totals), as the standard requires. Unit prices and quantities keep their decimals.

> **Tip:** Call `calculateTotals()` on each line item, then call `calculateTotals()` on the invoice. This auto-fills `lineExtensionAmount`, `taxAmount`, `taxExclusiveAmount`, `taxInclusiveAmount`, and all invoice-level totals.

> **See also:** [Line discounts (BG-27) & B2B/B2C buyer party](docs/allowances-and-buyer.md) — how a line discount becomes a valid `cac:AllowanceCharge`, and how the buyer party differs between Standard (B2B) and Simplified (B2C) invoices.

### Zero-rated, Exempt & Out-of-scope Items

Every line is standard-rated (`S`) by default. Use `setTaxCategory()` for other VAT categories. `Z`, `E` and `O` lines are always taxed at 0% and need a ZATCA exemption reason code. The reason text is filled in from ZATCA's official wording, except for `VATEX-SA-OOS`, where you must supply your own text.

```php
use KhaledHajSalem\Zatca\Support\TaxCategory;

// Zero-rated export
$line->setTaxCategory(TaxCategory::ZERO_RATED, 'VATEX-SA-32');

// Exempt financial service
$line->setTaxCategory(TaxCategory::EXEMPT, 'VATEX-SA-29');

// Out of scope (reason text required)
$line->setTaxCategory(TaxCategory::OUT_OF_SCOPE, 'VATEX-SA-OOS', 'Government fee not subject to VAT');
```

| Category | Meaning | Reason codes |
|----------|---------|--------------|
| `S` | Standard rated | none |
| `Z` | Zero rated | `VATEX-SA-32`, `VATEX-SA-33`, `VATEX-SA-34-1` … `VATEX-SA-34-5`, `VATEX-SA-35`, `VATEX-SA-36`, `VATEX-SA-EDU`, `VATEX-SA-HEA`, `VATEX-SA-MLTRY` |
| `E` | Exempt | `VATEX-SA-29`, `VATEX-SA-29-7`, `VATEX-SA-30` |
| `O` | Out of scope | `VATEX-SA-OOS` |

You can mix categories in one invoice. `calculateTotals()` builds one VAT breakdown entry (`cac:TaxSubtotal`) per category, rate and reason, and the XML includes it automatically. For `VATEX-SA-EDU` and `VATEX-SA-HEA`, ZATCA also requires the buyer's national ID (`NAT`).

A document-level allowance or charge uses the first line's category unless you set `tax_category` (and `tax_percent` for `S`):

```php
$invoiceData->addAllowance(['amount' => 10.00, 'reason' => 'discount', 'tax_category' => 'S', 'tax_percent' => 15.0]);
```

### Previous Invoice Hash (PIH) Chain

Every invoice references the hash of the previous invoice to create a tamper-proof chain:

```php
// Invoice 1 (first invoice — no previous)
$invoice1->setPreviousInvoiceHash(InvoiceData::FIRST_INVOICE_HASH); // base64(hex SHA-256 of '0'), BR-KSA-26
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

## Validation

`validate()` checks an invoice against the ZATCA business rules **before** anything is sent, and lists every problem at once. You don't have to wait for ZATCA to reject the invoice one error at a time.

```php
$issues = $invoiceData->validate();

foreach ($issues as $issue) {
    echo "{$issue['severity']} [{$issue['rule']}] {$issue['field']}: {$issue['message']}\n";
}
// error [BR-KSA-40] seller.vat_number: Seller VAT number must be 15 digits, starting and ending with 3.
// error [BR-KSA-81] buyer.party_identification: Buyer ID is required for tax invoices when the buyer has no VAT number.
```

- **`error`**: ZATCA rejects the invoice.
- **`warning`**: accepted, but probably not what you want (e.g. a credit note without a real reason).

Set `'validate' => true` in the `ZatcaManager` config to validate automatically in `processInvoice()`. Errors then throw a `ZatcaValidationException` before signing or sending. Warnings never block.

```php
use KhaledHajSalem\Zatca\Exceptions\ZatcaValidationException;

try {
    $result = $zatcaManager->processInvoice($invoiceData);
} catch (ZatcaValidationException $e) {
    $errors = $e->getIssues(); // same format as validate()
}
```

**What is checked:**

| Area | Rules |
|------|-------|
| Header | Invoice number, issue date (valid, not in the future), issue time format, date formats, currency codes, ICV digits, previous invoice hash (BR-02/03, BR-KSA-04/26/34/70, BR-KSA-F-01, BR-CL-04, BR-KSA-EN16931-02) |
| Seller | Name, VAT number format, identification and scheme, full address, 4-digit building number, 5-digit postal code, country code (BR-06, BR-KSA-08/09/37/39/40/66, BR-CL-14) |
| Buyer | Name and address on tax invoices, VAT number format, ID when there is no VAT number, ID scheme, Saudi address fields (BR-KSA-10/14/42/44/63/67/81) |
| Notes | Billing reference for credit/debit notes, reason warning (BR-KSA-56, BR-55, BR-KSA-17) |
| Payment | Payment means codes 10, 30, 42, 48, 1 (BR-KSA-16) |
| Lines | At least one line, item names, no negative values, standard rate above 0%, national ID for education/healthcare exemptions (BR-16, BR-25, BR-KSA-F-04, BR-KSA-DEC-02, BR-KSA-25/49) |
| Allowances/charges | Non-negative amounts, standard rate above 0% (BR-S-06/07) |
| Flags & dates | Flag combinations, supply dates, simplified summary requirements, prepayment date/time (BR-KSA-07/31/36/46/71/72, BR-KSA-F-05) |

Character limits (BR-KSA-F-06) come from ZATCA's separate Data Dictionary and are not checked.

---

## Error Handling

```php
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;
use KhaledHajSalem\Zatca\Exceptions\CertificateBuilderException;
use KhaledHajSalem\Zatca\Exceptions\ZatcaApiException;
use KhaledHajSalem\Zatca\Exceptions\ZatcaValidationException;

try {
    $result = $zatcaManager->processInvoice($invoiceData);
} catch (ZatcaValidationException $e) {
    // Invoice data breaks ZATCA rules (only with 'validate' => true)
    print_r($e->getIssues());
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
│   │   ├── ZatcaValidationException.php # Thrown by processInvoice() with 'validate' => true
│   │   └── ZatcaStorageException.php
│   ├── Services/                      # External services
│   │   ├── ZatcaAPIService.php        # ZATCA API client (clearance, reporting, compliance)
│   │   └── Storage.php                # File storage helper
│   ├── Validation/
│   │   └── InvoiceValidator.php       # Business-rule checks before submission
│   ├── Support/                       # Internal support classes
│   │   ├── Certificate.php            # Certificate loading & hashing
│   │   ├── CertificateBuilder.php     # CSR & private key generation
│   │   ├── InvoiceExtension.php       # UBL XML extension handling
│   │   ├── InvoiceSignatureBuilder.php # XMLDsig signature builder
│   │   ├── InvoiceSigner.php          # Signs XML, generates QR & hash
│   │   ├── QRCodeGenerator.php        # TLV-encoded QR code generation
│   │   ├── Money.php                  # Half-up rounding & decimal formatting
│   │   ├── TaxCategory.php            # VAT categories (S/Z/E/O) & exemption reason codes
│   │   └── QRCodeTags/               # Individual QR code tag classes
│   ├── ZatcaInvoice.php               # UBL 2.1 XML generator
│   └── ZatcaManager.php               # Main orchestrator (the class you use)
├── examples/
│   ├── basic-usage.php                # Complete working example with HTML output
│   ├── certificate-generation.php     # CSR generation example
│   └── invoice-types.php              # Standard, simplified, credit, debit, prepayment
├── docs/
│   ├── API.md                         # Detailed API reference
│   └── allowances-and-buyer.md        # Line discounts (BG-27) & B2B/B2C buyer party
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
# ZATCA PHP Package — API Reference

> This document is a detailed API reference for every public class and method. For a getting-started guide, see [README.md](../README.md).

## Table of Contents

- [ZatcaManager](#zatcamanager)
- [ZatcaInvoice](#zatcainvoice)
- [ZatcaAPIService](#zatcaapiservice)
- [Data Classes](#data-classes)
  - [InvoiceData](#invoicedata)
  - [SellerData](#sellerdata)
  - [BuyerData](#buyerdata)
  - [InvoiceLineData](#invoicelinedata)
- [Support Classes](#support-classes)
  - [CertificateBuilder](#certificatebuilder)
  - [Certificate](#certificate)
  - [InvoiceSigner](#invoicesigner)
- [Exceptions](#exceptions)
- [ZATCA API Environments](#zatca-api-environments)

---

## ZatcaManager

`KhaledHajSalem\Zatca\ZatcaManager`

The main class most developers will use. It orchestrates the full workflow: XML generation → signing → API submission.

### Constructor

```php
public function __construct(array $config = [])
```

| Config Key | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `environment` | `string` | Yes | `'sandbox'` | `'sandbox'`, `'simulation'`, or `'production'` |
| `certificate_path` | `string` | Yes | — | Absolute path to certificate PEM file |
| `private_key_path` | `string` | Yes | — | Absolute path to private key PEM file |
| `secret` | `string` | Yes | — | API secret key from ZATCA |
| `timeout` | `int` | No | `30` | HTTP timeout in seconds |
| `verify_ssl` | `bool` | No | `true` | Verify SSL certificates |
| `allow_warnings` | `bool` | No | `true` | Accept responses with warning status |

**Throws:** `ZatcaException` if required config is missing or files don't exist.

### processInvoice()

```php
public function processInvoice(InvoiceData $invoiceData, bool $isRetry = false): array
```

Generates XML, signs it, and submits to ZATCA. Automatically determines whether to use clearance (standard) or reporting (simplified).

**Returns:**

| Key | Type | Description |
|-----|------|-------------|
| `uuid` | `string` | Auto-generated invoice UUID |
| `invoice_hash` | `string` | Base64-encoded SHA-256 hash. **Save this as PIH for the next invoice.** |
| `qr_code` | `string` | Base64-encoded TLV QR code |
| `xml` | `string` | Signed XML. For cleared invoices, this is the ZATCA-returned XML. |
| `response` | `array` | Raw ZATCA API response (see below) |
| `is_clearance_required` | `bool` | `true` for standard, `false` for simplified |

**ZATCA response structure:**

```php
$result['response'] = [
    'validationResults' => [
        'status' => 'PASS',         // 'PASS', 'WARNING', or 'ERROR'
        'infoMessages' => [...],
        'warningMessages' => [...],
        'errorMessages' => [...],
    ],
    'reportingStatus' => 'REPORTED',  // For simplified invoices
    'clearanceStatus' => 'CLEARED',   // For standard invoices
    'clearedInvoice' => '...',        // Base64 cleared XML (standard only)
];
```

**Throws:** `ZatcaException`

> **Note:** Certificate requests (`requestComplianceCertificate`, `requestProductionCertificate`) are handled by [`ZatcaAPIService`](#zatcaapiservice) directly, since they are needed *before* a certificate exists to construct `ZatcaManager`.

### validateInvoiceCompliance()

```php
public function validateInvoiceCompliance(string $signedXml, string $invoiceHash, string $uuid): array
```

Validates a signed invoice against ZATCA compliance rules without submitting it.

**Returns:** ZATCA validation response array.

### getApiService()

```php
public function getApiService(): ZatcaAPIService
```

Returns the underlying API service instance for advanced usage.

### getCertificate()

```php
public function getCertificate(): Certificate
```

Returns the loaded Certificate instance.

---

## ZatcaInvoice

`KhaledHajSalem\Zatca\ZatcaInvoice`

Generates UBL 2.1 compliant invoice XML from an `InvoiceData` object.

### generateXml()

```php
public function generateXml(InvoiceData $invoiceData, ?string $uuid = null): string
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$invoiceData` | `InvoiceData` | Fully populated invoice data |
| `$uuid` | `string\|null` | Invoice UUID. Auto-generated if `null`. |

**Returns:** UBL 2.1 XML string.

> **Note:** You typically don't call this directly — `ZatcaManager::processInvoice()` calls it for you.

---

## ZatcaAPIService

`KhaledHajSalem\Zatca\Services\ZatcaAPIService`

Low-level ZATCA API client. Use this directly only if you need fine-grained control over API calls.

### Constructor

```php
public function __construct(string $environment = 'sandbox')
```

**Throws:** `InvalidArgumentException` if environment is not `sandbox`, `simulation`, or `production`.

### requestComplianceCertificate()

```php
public function requestComplianceCertificate(string $csr, string $otp): ComplianceCertificateResult
```

**Returns:** `ComplianceCertificateResult` with methods: `getCertificate()`, `getSecret()`, `getRequestId()`.

### requestProductionCertificate()

```php
public function requestProductionCertificate(string $certificate, string $secret, string $complianceRequestId): ProductionCertificateResult
```

**Returns:** `ProductionCertificateResult` with methods: `getCertificate()`, `getSecret()`, `getRequestId()`.

### validateInvoiceCompliance()

```php
public function validateInvoiceCompliance(string $certificate, string $secret, string $signedInvoice, string $invoiceHash, string $uuid): array
```

### clearInvoice()

```php
public function clearInvoice(string $certificate, string $secret, string $signedInvoice, string $invoiceHash, string $uuid): array
```

Submits a standard invoice for clearance.

### reportInvoice()

```php
public function reportInvoice(string $certificate, string $secret, string $signedInvoice, string $invoiceHash, string $uuid): array
```

Submits a simplified invoice for reporting.

### setWarningHandling()

```php
public function setWarningHandling(bool $allow): void
```

When `true` (default), responses with warning status are accepted. When `false`, warnings throw exceptions.

### setDebugMode()

```php
public function setDebugMode(bool $debug): void
```

When `true`, prints detailed request/response information to stdout. Useful for debugging API issues.

---

## Data Classes

### InvoiceData

`KhaledHajSalem\Zatca\Data\InvoiceData`

All setters return `$this` for method chaining.

#### Invoice Type Methods

| Method | Sets | Value |
|--------|------|-------|
| `standard()` | `invoiceTypeName` | `'0100000'` (B2B, clearance required) |
| `simplified()` | `invoiceTypeName` | `'0200000'` (B2C, reporting only) |
| `taxInvoice()` | `invoiceTypeCode` | `'388'` |
| `creditNote()` | `invoiceTypeCode` | `'381'` |
| `debitNote()` | `invoiceTypeCode` | `'383'` |
| `prepaymentInvoice()` | `invoiceTypeCode` | `'386'` |

#### Invoice Type Checkers

| Method | Returns |
|--------|---------|
| `isStandard(): bool` | `true` if type name is `'0100000'` |
| `isSimplified(): bool` | `true` if type name is `'0200000'` |
| `isCreditNote(): bool` | `true` if type code is `'381'` |
| `isDebitNote(): bool` | `true` if type code is `'383'` |
| `isCreditOrDebitNote(): bool` | `true` if credit or debit |

#### Setters

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `setInvoiceNumber($v)` | `string` | `''` | Invoice number |
| `setIssueDate($v)` | `string` | `''` | Format: `Y-m-d` |
| `setIssueTime($v)` | `string` | `''` | Format: `H:i:s` |
| `setDueDate($v)` | `string` | `''` | Format: `Y-m-d` |
| `setCurrencyCode($v)` | `string` | `'SAR'` | ISO 4217 |
| `setDocumentCurrencyCode($v)` | `string` | `'SAR'` | Usually same as currency |
| `setTaxCurrencyCode($v)` | `string` | `'SAR'` | Usually same as currency |
| `setInvoiceCounter($v)` | `string` | `'1'` | ICV: sequential counter |
| `setPreviousInvoiceHash($v)` | `string` | `'MA=='` | PIH: `'MA=='` for first invoice |
| `setInvoiceTypeName($v)` | `string` | `'0100000'` | Use `standard()` / `simplified()` instead |
| `setInvoiceTypeCode($v)` | `string` | `'388'` | Use `taxInvoice()` etc. instead |
| `setTransactionCode($v)` | `string` | `'0100000'` | KSA-2 transaction code |
| `setSeller($v)` | `SellerData` | `null` | Seller information |
| `setBuyer($v)` | `BuyerData` | `null` | Buyer information |

#### Totals (auto-calculated by `calculateTotals()`)

| Method | Type | Default |
|--------|------|---------|
| `setTaxTotalAmount($v)` | `float` | `0.0` |
| `setTaxExclusiveAmount($v)` | `float` | `0.0` |
| `setTaxInclusiveAmount($v)` | `float` | `0.0` |
| `setAllowanceTotalAmount($v)` | `float` | `0.0` |
| `setChargeTotalAmount($v)` | `float` | `0.0` |
| `setPayableAmount($v)` | `float` | `0.0` |
| `setLineCountNumeric($v)` | `int` | `0` |

#### Collection Methods

| Method | Description |
|--------|-------------|
| `addLine(InvoiceLineData $line)` | Add a line item. Auto-updates `lineCountNumeric`. |
| `addBillingReference(array $ref)` | Add billing reference. Keys: `id`, `uuid`. Required for credit/debit notes. |
| `addPaymentMeans(array $pm)` | Add payment means. Keys: `code`, `instruction_note`, `id` (optional), `due_date` (optional). |
| `addDocumentReference(array $ref)` | Add document reference. |
| `addAllowance(array $allowance)` | Add document-level allowance. Key: `amount`. |
| `addCharge(array $charge)` | Add document-level charge. Key: `amount`. |
| `setDeliveryInfo(array $info)` | Set delivery information. |

#### calculateTotals()

```php
public function calculateTotals(): self
```

Sums all line items to compute invoice-level totals:
- `taxExclusiveAmount` = sum of line `taxExclusiveAmount`
- `taxTotalAmount` = sum of line `taxAmount`
- `allowanceTotalAmount` = sum of line allowances + document allowances
- `chargeTotalAmount` = sum of line charges + document charges
- `taxInclusiveAmount` = taxExclusive + tax + charges - allowances
- `payableAmount` = taxInclusiveAmount

**Call this after adding all lines, allowances, and charges.**

---

### SellerData

`KhaledHajSalem\Zatca\Data\SellerData`

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `setRegistrationName($v)` | `string` | `''` | Company legal name |
| `setVatNumber($v)` | `string` | `''` | 15-digit VAT number |
| `setPartyIdentification($v)` | `string` | `''` | ID value (e.g., CRN number) |
| `setPartyIdentificationId($v)` | `string` | `'CRN'` | Scheme: `CRN`, `VAT`, `TIN`, `NAT`, `IQA`, `GCC`, `PAS`, `MOM`, `MLS`, `SAG`, `700`, `OTH` |
| `setAddress($v)` | `string` | `''` | Full address string |
| `setStreetName($v)` | `string` | `''` | Street name |
| `setBuildingNumber($v)` | `string` | `''` | Building number |
| `setCityName($v)` | `string` | `''` | City name |
| `setPostalZone($v)` | `string` | `''` | Postal/ZIP code |
| `setCountryCode($v)` | `string` | `'SA'` | 2-letter country code |
| `setPlotIdentification($v)` | `string` | `''` | Plot identification |
| `setCitySubdivisionName($v)` | `string` | `''` | District/subdivision name |

All setters have corresponding getters (e.g., `getRegistrationName(): string`).

---

### BuyerData

`KhaledHajSalem\Zatca\Data\BuyerData`

Same methods as [SellerData](#sellerdata). Default `partyIdentificationId` is `'TIN'` instead of `'CRN'`.

For simplified invoices, `vatNumber` is optional.

---

### InvoiceLineData

`KhaledHajSalem\Zatca\Data\InvoiceLineData`

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `setId($v)` | `int` | `0` | Line number (1, 2, 3...) |
| `setItemName($v)` | `string` | `''` | Item name |
| `setDescription($v)` | `string` | `''` | Item description |
| `setQuantity($v)` | `float` | `0.0` | Quantity |
| `setUnitPrice($v)` | `float` | `0.0` | Unit price (tax-exclusive) |
| `setTaxPercent($v)` | `float` | `0.0` | VAT percentage (e.g., `15.0`) |
| `setUnitCode($v)` | `string` | `'EA'` | UN/ECE unit code |
| `setItemCode($v)` | `string` | `''` | Item code |
| `setAllowanceAmount($v)` | `float` | `0.0` | Line-level discount |
| `setChargeAmount($v)` | `float` | `0.0` | Line-level surcharge |
| `addTaxCategory(array $cat)` | `array` | `[]` | Additional tax categories |

#### calculateTotals()

```php
public function calculateTotals(): self
```

Computes from `quantity`, `unitPrice`, `taxPercent`, `allowanceAmount`, `chargeAmount`:

```
lineExtensionAmount = quantity × unitPrice
taxExclusiveAmount  = lineExtensionAmount - allowanceAmount + chargeAmount
taxAmount           = taxExclusiveAmount × (taxPercent / 100)
taxInclusiveAmount  = taxExclusiveAmount + taxAmount
```

**Set `allowanceAmount` and `chargeAmount` before calling `calculateTotals()`.**

You can also set all amounts manually instead of calling `calculateTotals()`:

| Method | Type | Description |
|--------|------|-------------|
| `setLineExtensionAmount($v)` | `float` | qty × price |
| `setTaxAmount($v)` | `float` | Tax amount |
| `setTaxExclusiveAmount($v)` | `float` | Before tax |
| `setTaxInclusiveAmount($v)` | `float` | After tax |

---

## Support Classes

### CertificateBuilder

`KhaledHajSalem\Zatca\Support\CertificateBuilder`

Generates a CSR (Certificate Signing Request) and private key for ZATCA onboarding.

| Method | Type | Description |
|--------|------|-------------|
| `setOrganizationIdentifier($v)` | `string` | 15 digits, starts and ends with `3` |
| `setSerialNumber($solution, $model, $serial)` | `string, string, string` | Device serial number |
| `setCommonName($v)` | `string` | Company name |
| `setCountryName($v)` | `string` | 2-letter code (e.g., `SA`) |
| `setOrganizationName($v)` | `string` | Organization name |
| `setOrganizationalUnitName($v)` | `string` | Department name |
| `setAddress($v)` | `string` | Full address |
| `setInvoiceType($v)` | `int` | 4 digits: `[Standard][Simplified][0][0]` — e.g., `1100` |
| `setProduction($v)` | `bool` | `false` for sandbox, `true` for production |
| `setBusinessCategory($v)` | `string` | e.g., `'Legal Entity'`, `'Technology'` |
| `generateAndSave($csrPath, $keyPath)` | `string, string` | Saves CSR and private key to files |

---

### Certificate

`KhaledHajSalem\Zatca\Support\Certificate`

Loads and manages a signing certificate.

#### Constructor

```php
public function __construct(string $rawCert, string $privateKeyStr, string $secretKey)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$rawCert` | `string` | Raw PEM certificate content (`file_get_contents('cert.pem')`) |
| `$privateKeyStr` | `string` | Raw PEM private key content |
| `$secretKey` | `string` | API secret key from ZATCA |

#### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getRawCertificate()` | `string` | Raw PEM certificate |
| `getPrivateKey()` | `string` | Raw PEM private key |
| `getCertHash()` | `string` | Base64-encoded SHA-256 hash of certificate content |
| `getRawPublicKey()` | `string` | Base64-encoded public key |
| `getAuthHeader()` | `string` | `Basic ...` authorization header value |

---

### InvoiceSigner

`KhaledHajSalem\Zatca\Support\InvoiceSigner`

Signs invoice XML with XMLDsig/XAdES and generates QR codes.

#### signInvoice()

```php
public static function signInvoice(string $xmlInvoice, Certificate $certificate): self
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$xmlInvoice` | `string` | UBL 2.1 invoice XML |
| `$certificate` | `Certificate` | Loaded certificate instance |

**Returns:** `InvoiceSigner` instance.

#### Instance Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getXML()` | `string` | Signed XML string |
| `getQRCode()` | `string` | Base64-encoded TLV QR code |
| `getHash()` | `string` | Base64-encoded SHA-256 invoice hash |

---

## Exceptions

All exceptions extend `ZatcaException` and provide `getContext(): array` for structured error details.

| Exception | Namespace | When thrown |
|-----------|-----------|------------|
| `ZatcaException` | `Exceptions\ZatcaException` | Base exception. Missing config, processing errors. |
| `CertificateBuilderException` | `Exceptions\CertificateBuilderException` | CSR generation failures. |
| `ZatcaApiException` | `Exceptions\ZatcaApiException` | ZATCA API errors (HTTP, validation, auth). |
| `ZatcaStorageException` | `Exceptions\ZatcaStorageException` | File read/write failures. |

```php
try {
    $result = $zatcaManager->processInvoice($invoiceData);
} catch (\KhaledHajSalem\Zatca\Exceptions\ZatcaApiException $e) {
    $context = $e->getContext();
    // $context['endpoint'], $context['status_code'], $context['response']
} catch (\KhaledHajSalem\Zatca\Exceptions\ZatcaException $e) {
    echo $e->getMessage();
    echo json_encode($e->getContext());
}
```

---

## ZATCA API Environments

| Environment | Base URL | Use case |
|-------------|----------|----------|
| `sandbox` | `https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal` | Development & testing |
| `simulation` | `https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation` | Pre-production testing |
| `production` | `https://gw-fatoora.zatca.gov.sa/e-invoicing/core` | Live invoices |

API version: **V2**
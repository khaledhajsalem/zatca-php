# ZATCA PHP Package API Documentation

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Quick Start](#quick-start)
4. [Core Classes](#core-classes)
5. [Data Classes](#data-classes)
6. [Support Classes](#support-classes)
7. [Exceptions](#exceptions)
8. [Configuration](#configuration)
9. [Examples](#examples)
10. [Error Handling](#error-handling)

## Overview

The ZATCA PHP Package provides a comprehensive solution for creating, signing, and submitting invoices to the ZATCA (Saudi Arabia e-invoicing) platform. The package is designed to work with any PHP project without database dependencies.

## Installation

```bash
composer require khaledhajsalem/zatca-php
```

## Quick Start

```php
use KhaledHajSalem\Zatca\ZatcaManager;
use KhaledHajSalem\Zatca\Data\InvoiceData;

// Initialize manager
$zatcaManager = new ZatcaManager([
    'environment' => 'sandbox',
    'certificate_path' => '/path/to/certificate.pem',
    'private_key_path' => '/path/to/private.pem',
    'secret' => 'your-secret-key'
]);

// Create and process invoice
$invoiceData = new InvoiceData();
// ... set invoice data ...

$result = $zatcaManager->processInvoice($invoiceData);
```

## Core Classes

### ZatcaManager

The main orchestrator class that handles the complete ZATCA workflow.

#### Constructor

```php
public function __construct(array $config = [])
```

**Parameters:**
- `$config` (array): Configuration array with the following keys:
  - `environment` (string): API environment ('sandbox', 'simulation', 'production')
  - `certificate_path` (string): Path to certificate file
  - `private_key_path` (string): Path to private key file
  - `secret` (string): API secret key
  - `timeout` (int): HTTP timeout in seconds (default: 30)
  - `verify_ssl` (bool): Whether to verify SSL certificates (default: true)
  - `allow_warnings` (bool): Whether to allow warning responses (default: true)

#### Methods

##### processInvoice()

```php
public function processInvoice(InvoiceData $invoiceData, bool $isRetry = false): array
```

Processes a complete invoice workflow including XML generation, signing, and submission.

**Parameters:**
- `$invoiceData` (InvoiceData): Invoice data object
- `$isRetry` (bool): Whether this is a retry attempt

**Returns:**
- `array`: Result containing QR code, hash, XML, UUID, and API response

##### requestComplianceCertificate()

```php
public function requestComplianceCertificate(string $csr, string $otp): array
```

Requests a compliance certificate from ZATCA.

**Parameters:**
- `$csr` (string): Certificate signing request content
- `$otp` (string): One-time password from ZATCA

**Returns:**
- `array`: Certificate data including certificate, secret, and request ID

##### requestProductionCertificate()

```php
public function requestProductionCertificate(string $complianceRequestId): array
```

Requests a production certificate using compliance credentials.

**Parameters:**
- `$complianceRequestId` (string): Compliance request ID

**Returns:**
- `array`: Production certificate data

##### validateInvoiceCompliance()

```php
public function validateInvoiceCompliance(string $signedXml, string $invoiceHash, string $uuid): array
```

Validates invoice compliance with ZATCA regulations.

**Parameters:**
- `$signedXml` (string): Signed invoice XML
- `$invoiceHash` (string): Invoice hash
- `$uuid` (string): Invoice UUID

**Returns:**
- `array`: Validation response from ZATCA



### ZatcaInvoice

Generates UBL 2.1 compliant invoice XML.

#### Methods

##### generateXml()

```php
public function generateXml(InvoiceData $invoiceData): string
```

Generates UBL 2.1 compliant invoice XML.

**Parameters:**
- `$invoiceData` (InvoiceData): Invoice data object

**Returns:**
- `string`: Generated XML content

## Data Classes

### InvoiceData

Data class for invoice information.

#### Properties

- `invoiceNumber` (string): Invoice number
- `issueDate` (string): Issue date (YYYY-MM-DD)
- `issueTime` (string): Issue time (HH:MM:SS)
- `dueDate` (string): Due date (YYYY-MM-DD)
- `currencyCode` (string): Currency code (default: 'SAR')
- `invoiceTypeCode` (string): Invoice type code (default: '388')
- `invoiceTypeName` (string): Invoice type name (default: '0100000' for Standard Tax Invoice)
- `documentCurrencyCode` (string): Document currency code
- `taxCurrencyCode` (string): Tax currency code
- `lineCountNumeric` (int): Number of invoice lines
- `taxTotalAmount` (float): Total tax amount
- `taxExclusiveAmount` (float): Tax exclusive amount
- `taxInclusiveAmount` (float): Tax inclusive amount
- `allowanceTotalAmount` (float): Total allowance amount
- `chargeTotalAmount` (float): Total charge amount
- `payableAmount` (float): Payable amount
- `seller` (SellerData): Seller information
- `buyer` (BuyerData): Buyer information
- `lines` (array): Invoice line items

#### Methods

##### Setters

All properties have corresponding setter methods that return `$this` for method chaining:

```php
$invoiceData->setInvoiceNumber('INV-001')
    ->setIssueDate('2024-01-15')
    ->setIssueTime('10:30:00')
    ->setCurrencyCode('SAR');
```

##### addLine()

```php
public function addLine(InvoiceLineData $line): self
```

Adds an invoice line item.

##### calculateTotals()

```php
public function calculateTotals(): self
```

Calculates invoice totals from line items.

### SellerData

Data class for seller information.

#### Properties

- `registrationName` (string): Company registration name
- `vatNumber` (string): VAT registration number
- `partyIdentification` (string):  party Identification
- `partyIdentificationId` (string): party Identification ID
- `address` (string): Full address
- `countryCode` (string): Country code (default: 'SA')
- `cityName` (string): City name
- `postalZone` (string): Postal code
- `streetName` (string): Street name
- `buildingNumber` (string): Building number
- `plotIdentification` (string): Plot identification
- `citySubdivisionName` (string): City subdivision name

### BuyerData

Data class for buyer information. Same properties as SellerData.

### InvoiceLineData

Data class for invoice line items.

#### Properties

- `id` (int): Line item ID
- `itemName` (string): Item name
- `description` (string): Item description
- `quantity` (float): Quantity
- `unitPrice` (float): Unit price
- `lineExtensionAmount` (float): Line extension amount
- `taxAmount` (float): Tax amount
- `taxPercent` (float): Tax percentage
- `taxExclusiveAmount` (float): Tax exclusive amount
- `taxInclusiveAmount` (float): Tax inclusive amount
- `allowanceAmount` (float): Allowance amount
- `chargeAmount` (float): Charge amount
- `unitCode` (string): Unit code (default: 'EA')
- `itemCode` (string): Item code

#### Methods

##### calculateTotals()

```php
public function calculateTotals(): self
```

Calculates line item totals.

## Support Classes

### CertificateBuilder

Generates CSR and private key for ZATCA certificates.

#### Methods

##### setOrganizationIdentifier()

```php
public function setOrganizationIdentifier(string $identifier): self
```

Sets the organization identifier (15 digits starting and ending with 3).

##### setSerialNumber()

```php
public function setSerialNumber(string $solutionName, string $model, string $serialNumber): self
```

Sets the serial number using solution name, model, and serial.

##### generateAndSave()

```php
public function generateAndSave(string $csrPath = 'certificate.csr', string $privateKeyPath = 'private.pem'): void
```

Generates and saves CSR and private key to files.

### Certificate

Handles certificate operations and authentication.

#### Constructor

```php
public function __construct(string $rawCert, string $privateKeyStr, string $secretKey)
```

**Parameters:**
- `$rawCert` (string): Raw certificate content
- `$privateKeyStr` (string): Private key string
- `$secretKey` (string): Secret key for authentication

#### Methods

##### getAuthHeader()

```php
public function getAuthHeader(): string
```

Creates the authorization header for API requests.

##### getCertHash()

```php
public function getCertHash(): string
```

Generates a hash of the certificate.

##### getRawPublicKey()

```php
public function getRawPublicKey(): string
```

Gets the raw public key in base64 format.

### InvoiceSigner

Signs invoice XML and generates QR codes.

#### Methods

##### signInvoice()

```php
public static function signInvoice(string $xmlInvoice, Certificate $certificate): self
```

Signs the invoice XML and returns an InvoiceSigner object.

**Parameters:**
- `$xmlInvoice` (string): Invoice XML as string
- `$certificate` (Certificate): Certificate for signing

**Returns:**
- `InvoiceSigner`: Signed invoice object

##### getXML()

```php
public function getXML(): string
```

Gets the signed XML string.

##### getQRCode()

```php
public function getQRCode(): string
```

Gets the QR code (base64 encoded).

##### getHash()

```php
public function getHash(): string
```

Gets the invoice hash (base64 encoded).

## Exceptions

### ZatcaException

Base exception class for ZATCA package.

#### Methods

##### getContext()

```php
public function getContext(): array
```

Gets additional context information about the error.

### CertificateBuilderException

Exception thrown when certificate building fails.

### ZatcaApiException

Exception thrown when ZATCA API calls fail.

### ZatcaStorageException

Exception thrown when storage operations fail.

## Configuration

### Environment Settings

```php
$config = [
    'environment' => 'sandbox', // sandbox, simulation, production
    'timeout' => 30,
    'verify_ssl' => true,
    'allow_warnings' => true
];
```

### Certificate Configuration

```php
$certConfig = [
    'certificate_path' => '/path/to/certificate.pem',
    'private_key_path' => '/path/to/private.pem',
    'secret' => 'your-secret-key',
    'organization_identifier' => '300000000000003'
];
```

## Error Handling

```php
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;
use KhaledHajSalem\Zatca\Exceptions\CertificateBuilderException;
use KhaledHajSalem\Zatca\Exceptions\ZatcaApiException;

try {
    $result = $zatcaManager->processInvoice($invoiceData);
} catch (CertificateBuilderException $e) {
    echo "Certificate error: " . $e->getMessage();
} catch (ZatcaApiException $e) {
    echo "API error: " . $e->getMessage();
    echo "Response: " . json_encode($e->getContext());
} catch (ZatcaException $e) {
    echo "General error: " . $e->getMessage();
}
```

## Invoice Types

The package supports all ZATCA invoice types with proper clearance handling:

### Standard Tax Invoice (B2B/B2G)
- **Code:** `388`
- **Name:** `"0100000"`
- **Clearance:** Required before distribution
- **Use Case:** Business-to-business or business-to-government transactions

### Simplified Tax Invoice (B2C)
- **Code:** `388`
- **Name:** `"0200000"`
- **Clearance:** Not required, report within 24 hours
- **Use Case:** Business-to-consumer transactions

### Debit Note
- **Code:** `383`
- **Clearance:** Not required
- **Use Case:** Additional charges or corrections

### Credit Note
- **Code:** `381`
- **Clearance:** Not required
- **Use Case:** Returns, refunds, or corrections

### Prepayment Invoice
- **Code:** `386`
- **Clearance:** Not required
- **Use Case:** Advance payments

## Examples

See the `examples/` directory for complete working examples:

- `basic-usage.php`: Basic invoice processing
- `certificate-generation.php`: Certificate generation workflow
- `invoice-types.php`: Different invoice types and clearance requirements 
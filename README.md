# ZATCA PHP Package

[![Packagist Version](https://img.shields.io/packagist/v/khaledhajsalem/zatca-php)](https://packagist.org/packages/khaledhajsalem/zatca-php)
[![Downloads](https://img.shields.io/packagist/dt/khaledhajsalem/zatca-php)](https://packagist.org/packages/khaledhajsalem/zatca-php)
[![License](https://img.shields.io/packagist/l/khaledhajsalem/zatca-php)](https://packagist.org/packages/khaledhajsalem/zatca-php)
[![ZATCA Phase 2](https://img.shields.io/badge/ZATCA-Phase%202%20Compliant-success)](https://zatca.gov.sa)

A comprehensive PHP package for ZATCA (Saudi Arabia e-invoicing) invoice processing, signing, and submission. This package provides a complete solution for creating, signing, and submitting invoices to the ZATCA platform.

## Features

- ✅ Create UBL 2.1 compliant invoice XML
- ✅ Digital signature generation and validation
- ✅ QR code generation for invoices
- ✅ Certificate management and CSR generation
- ✅ ZATCA API integration (compliance, production, reporting)
- ✅ Support for all invoice types (standard, simplified, etc.)
- ✅ Comprehensive error handling and logging
- ✅ No database dependencies - works with any project
- ✅ PSR-4 compliant and modern PHP 8.0+

### Core Components

- **ZatcaManager**: Main orchestrator that handles the complete workflow
- **ZatcaInvoice**: Generates UBL 2.1 compliant invoice XML
- **Data Classes**: Hold invoice, seller, buyer, and line item information
- **Support Classes**: Handle digital signatures, certificates, and QR codes
- **Services**: API integration and file storage
- **Exceptions**: Structured error handling with context

## Installation

```bash
composer require khaledhajsalem/zatca-php
```

## Quick Start

### 1. Basic Invoice Creation

```php
use KhaledHajSalem\Zatca\ZatcaInvoice;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;

// Create invoice data
$invoiceData = new InvoiceData();
$invoiceData->setInvoiceNumber('INV-001')
    ->setIssueDate('2024-01-15')
    ->setIssueTime('10:30:00')
    ->setDueDate('2024-02-15')
    ->setCurrencyCode('SAR')
    ->setInvoiceTypeCode('388') // Standard Tax Invoice
    ->setInvoiceTypeName('0100000') // Standard Tax Invoice (requires clearance)
    ->setDocumentCurrencyCode('SAR')
    ->setTaxCurrencyCode('SAR')
    ->setInvoiceCounter('1')
    ->setTransactionCode('0100000')
    ->setPreviousInvoiceHash('MA=='); // PIH: Previous Invoice Hash (base64 encoded "0" for first invoice)

// Set seller information
$seller = new SellerData();
$seller->setRegistrationName('Your Company Name')
    ->setPartyIdentification('1020304050') //
    ->setVatNumber('3000000000000003')
    ->setPartyIdentificationId('CRN') // Commercial Registration Number
    ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
    ->setCountryCode('SA');

$invoiceData->setSeller($seller);

// Set buyer information
$buyer = new BuyerData();
$buyer->setRegistrationName('Customer Company')
    ->setPartyIdentification('3000000000000003') // VAT/Tax number
    ->setPartyIdentificationId('TIN') // Tax Identification Number
    ->setAddress('456 Customer Street, Jeddah, Saudi Arabia')
    ->setCountryCode('SA');

$invoiceData->setBuyer($buyer);

// Add invoice lines
$line1 = new InvoiceLineData();
$line1->setId(1)
    ->setItemName('Product 1')
    ->setQuantity(2)
    ->setUnitPrice(100.00)
    ->setLineExtensionAmount(200.00)
    ->setTaxAmount(30.00)
    ->setTaxPercent(15.0);

$invoiceData->addLine($line1);

// Create and generate XML
$zatcaInvoice = new ZatcaInvoice();
$xml = $zatcaInvoice->generateXml($invoiceData);

echo $xml;
```

### 2. Certificate Management

```php
use KhaledHajSalem\Zatca\CertificateBuilder;
use KhaledHajSalem\Zatca\Certificate;

// Generate CSR and private key
$builder = new CertificateBuilder();
$builder->setOrganizationIdentifier('300000000000003')
    ->setSerialNumber('MySolution', 'Model1', 'SN001')
    ->setCommonName('Your Company Name')
    ->setCountryName('SA')
    ->setOrganizationName('Your Company Name')
    ->setOrganizationalUnitName('IT Department')
    ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
    ->setInvoiceType(1100)
    ->setProduction(false) // true for production
    ->setBusinessCategory('1000');

$builder->generateAndSave('certificate.csr', 'private.pem');

// Load certificate for signing
$certificate = new Certificate(
    file_get_contents('certificate.pem'),
    file_get_contents('private.pem'),
    'your-secret-key'
);
```

### 3. Invoice Signing

```php
use KhaledHajSalem\Zatca\InvoiceSigner;

// Sign the invoice
$signer = InvoiceSigner::signInvoice($xml, $certificate);

$signedXml = $signer->getXML();
$qrCode = $signer->getQRCode();
$hash = $signer->getHash();

echo "Signed XML: " . $signedXml;
echo "QR Code: " . $qrCode;
echo "Hash: " . $hash;
```

### 4. ZATCA API Integration

```php
use KhaledHajSalem\Zatca\ZatcaAPIService;

// Initialize API service
$apiService = new ZatcaAPIService('sandbox'); // or 'simulation', 'production'

// Request compliance certificate
$complianceResult = $apiService->requestComplianceCertificate($csr, $otp);

// Validate invoice compliance
$validationResult = $apiService->validateInvoiceCompliance(
    $certificate,
    $secret,
    $signedXml,
    $hash,
    $uuid
);

// Clear invoice (for Standard Tax Invoices)
$clearanceResult = $apiService->clearInvoice(
    $certificate,
    $secret,
    $signedXml,
    $hash,
    $uuid
);

// Report invoice (for Simplified Tax Invoices)
$reportingResult = $apiService->reportInvoice(
    $certificate,
    $secret,
    $signedXml,
    $hash,
    $uuid
);
```

### 5. Complete Workflow

```php
use KhaledHajSalem\Zatca\ZatcaManager;

// Initialize ZATCA manager
$zatcaManager = new ZatcaManager([
    'environment' => 'sandbox',
    'certificate_path' => 'path/to/certificate.pem',
    'private_key_path' => 'path/to/private.pem',
    'secret' => 'your-secret-key'
]);

// Process complete invoice workflow
$result = $zatcaManager->processInvoice($invoiceData);

echo "QR Code: " . $result['qr_code'];
echo "Invoice Hash: " . $result['invoice_hash'];
echo "Signed XML: " . $result['xml'];
echo "API Response: " . json_encode($result['response']);
```

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

## Data Structures

### InvoiceData

```php
$invoiceData = new InvoiceData();
$invoiceData->setInvoiceNumber('INV-001')
    ->setIssueDate('2024-01-15')
    ->setIssueTime('10:30:00')
    ->setDueDate('2024-02-15')
    ->setCurrencyCode('SAR')
    ->setInvoiceTypeCode('388')
    ->setInvoiceTypeName('0100000')
    ->setDocumentCurrencyCode('SAR')
    ->setTaxCurrencyCode('SAR')
    ->setInvoiceCounter('1')
    ->setTransactionCode('0100000')
    ->setPreviousInvoiceHash('MA==') // PIH: Previous Invoice Hash (base64 encoded "0")
    ->setLineCountNumeric(1)
    ->setTaxTotalAmount(30.00)
    ->setTaxExclusiveAmount(200.00)
    ->setTaxInclusiveAmount(230.00)
    ->setAllowanceTotalAmount(0.00)
    ->setChargeTotalAmount(0.00)
    ->setPayableAmount(230.00);
```

### SellerData

```php
$seller = new SellerData();
$seller->setRegistrationName('Your Company Name')
    ->setPartyIdentification('123456789012345') // VAT/Tax number
    ->setPartyIdentificationId('CRN') // Commercial Registration Number
    ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
    ->setCountryCode('SA')
    ->setCityName('Riyadh')
    ->setPostalZone('12345')
    ->setStreetName('Main Street')
    ->setBuildingNumber('123')
    ->setPlotIdentification('PLOT-001')
    ->setCitySubdivisionName('District 1');
```

### BuyerData

```php
$buyer = new BuyerData();
$buyer->setRegistrationName('Customer Company')
    ->setPartyIdentification('987654321098765') // VAT/Tax number
    ->setPartyIdentificationId('TIN') // Tax Identification Number
    ->setAddress('456 Customer Street, Jeddah, Saudi Arabia')
    ->setCountryCode('SA')
    ->setCityName('Jeddah')
    ->setPostalZone('54321')
    ->setStreetName('Customer Street')
    ->setBuildingNumber('456')
    ->setPlotIdentification('PLOT-002')
    ->setCitySubdivisionName('District 2');
```

### Flexible Party Identification

The package supports flexible party identification with different scheme IDs for both sellers and buyers:

```php
// Seller with Commercial Registration Number
$seller->setPartyIdentification('311111111111113')
       ->setPartyIdentificationId('CRN');

// Buyer with Tax Identification Number
$buyer->setPartyIdentification('300000000000003')
      ->setPartyIdentificationId('TIN');

// Buyer with National ID
$buyer->setPartyIdentification('123456789012345')
      ->setPartyIdentificationId('NAT');

// Buyer with Iqama Number
$buyer->setPartyIdentification('987654321098765')
      ->setPartyIdentificationId('IQA');
```

**Available Scheme IDs:**
- `CRN`: Commercial Registration Number
- `TIN`: Tax Identification Number  
- `NAT`: National ID
- `IQA`: Iqama Number
- `GCC`: GCC ID
- `PAS`: Passport ID
- `OTH`: Other ID
- `MOM`: MOMRAH License
- `MLS`: MHRSD License
- `SAG`: MISA License
- `700`: 700 Number

**Default Values:**
- Sellers default to `CRN` (Commercial Registration Number)
- Buyers default to `TIN` (Tax Identification Number)

### Previous Invoice Hash (PIH)

The Previous Invoice Hash (PIH) is used to create a chain of invoices for validation:

```php
// First invoice (no previous invoice)
$invoice->setPreviousInvoiceHash('MA=='); // base64 encoded '0'

// Subsequent invoices (with previous hash)
$invoice->setPreviousInvoiceHash($previousInvoiceHash);
```

**PIH Rules:**
- **First invoice**: Use `'MA=='` as PIH (base64 encoded '0')
- **Subsequent invoices**: Use the hash of the previous invoice
- **Format**: Base64 encoded SHA-256 hash
- **Purpose**: Invoice chain validation and sequence integrity
- **ZATCA Requirement**: Helps verify invoice sequence and prevent tampering

**Usage Examples:**
```php
// Invoice 1 (first invoice)
$invoice1->setPreviousInvoiceHash('MA=='); // base64 encoded '0'

// Invoice 2 (references invoice 1)
$invoice2->setPreviousInvoiceHash('NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==');

// Invoice 3 (references invoice 2)
$invoice3->setPreviousInvoiceHash('YzFmNGM2NzcxYjk5OGRhMWZjYTVlZThmNzVjZTYzZWIwY2U4ZjFlYmQ1N2NkNzU1ZTEyM2I4ZWViMmE2YWQ4Mg==');
```

### InvoiceLineData

```php
$line = new InvoiceLineData();
$line->setId(1)
    ->setItemName('Product Name')
    ->setDescription('Product description')
    ->setQuantity(2)
    ->setUnitPrice(100.00)
    ->setLineExtensionAmount(200.00)
    ->setTaxAmount(30.00)
    ->setTaxPercent(15.0)
    ->setTaxExclusiveAmount(200.00)
    ->setTaxInclusiveAmount(230.00)
    ->setAllowanceAmount(0.00)
    ->setChargeAmount(0.00);
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

## Testing

```bash
# Run tests
composer test

# Run with coverage
composer test-coverage

# Static analysis
composer phpstan

# Code style check
composer cs-check

# Fix code style
composer cs-fix
```

## Package Structure

```
php-zatca-phase2/
├── src/
│   ├── Data/                    # Data classes for invoice information
│   │   ├── InvoiceData.php      # Main invoice data container
│   │   ├── SellerData.php       # Seller information
│   │   ├── BuyerData.php        # Buyer information
│   │   └── InvoiceLineData.php  # Invoice line items
│   ├── Exceptions/              # Exception classes
│   │   ├── ZatcaException.php   # Base exception class
│   │   ├── CertificateBuilderException.php
│   │   ├── ZatcaApiException.php
│   │   └── ZatcaStorageException.php
│   ├── Services/                # Service classes
│   │   ├── ZatcaAPIService.php  # ZATCA API integration
│   │   └── Storage.php          # File storage service
│   ├── Support/                 # Core signing and certificate classes
│   │   ├── Certificate.php      # Certificate handling
│   │   ├── CertificateBuilder.php # CSR generation
│   │   ├── InvoiceExtension.php # XML extension handling
│   │   ├── InvoiceSignatureBuilder.php # Signature XML builder
│   │   ├── InvoiceSigner.php    # Invoice signing
│   │   ├── QRCodeGenerator.php  # QR code generation
│   │   └── QRCodeTags/          # QR code tag classes
│   ├── ZatcaInvoice.php         # UBL 2.1 XML generator
│   └── ZatcaManager.php         # Main workflow orchestrator
├── examples/                    # Usage examples
│   ├── basic-usage.php          # Basic invoice processing
│   ├── certificate-generation.php # Certificate workflow
│   └── invoice-types.php        # Different invoice types
├── docs/                        # Documentation
│   └── API.md                   # Detailed API documentation
├── tests/                       # Unit tests
│   └── ZatcaInvoiceTest.php     # Test cases
├── composer.json                # Package configuration
├── README.md                    # This file
├── LICENSE                      # MIT License
├── CHANGELOG.md                 # Version history
├── .gitignore                   # Git ignore rules
├── phpunit.xml                  # PHPUnit configuration
├── phpstan.neon                 # PHPStan configuration
└── phpcs.xml                    # Code style configuration
```


## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## Acknowledgments

This package includes some code snippets and inspiration from the excellent work done by:

- **[php-zatca-xml](https://github.com/Saleh7/php-zatca-xml)** by [Saleh7](https://github.com/Saleh7) - Portions of XML generation and ZATCA compliance logic were adapted from this project.

We appreciate the open-source community's contributions to ZATCA e-invoicing solutions in PHP.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For support and questions, please open an issue on GitHub or contact me:

- **Email:** [khaledhajsalem@hotmail.com](mailto:khaledhajsalem@hotmail.com)
- **GitHub Issues:** [Create an issue](https://github.com/khaledhajsalem/zatca-php/issues)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes and version history. 
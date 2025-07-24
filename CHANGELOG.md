# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-07-24

### Added
- Initial release of ZATCA PHP package
- Complete UBL 2.1 compliant invoice XML generation
- Digital signature generation and validation
- QR code generation for invoices
- Certificate management and CSR generation
- ZATCA API integration (compliance, production, reporting)
- Support for all invoice types (standard, simplified, etc.)
- Comprehensive error handling
- No database dependencies - works with any project
- PSR-4 compliant and modern PHP 8.0+

### Features
- `ZatcaInvoice` class for XML generation
- `ZatcaManager` class for complete workflow orchestration
- `CertificateBuilder` for CSR and private key generation
- `InvoiceSigner` for digital signature creation
- `ZatcaAPIService` for API communication
- Data classes for invoice, seller, buyer, and line items
- Exception handling with context information
- Simple storage service for file operations

### Technical Details
- Uses phpseclib for cryptographic operations
- Guzzle HTTP client for API communication
- chillerlan/php-qrcode for QR code generation
- Support for sandbox, simulation, and production environments
- Comprehensive XML namespace handling
- Proper UBL 2.1 schema compliance 
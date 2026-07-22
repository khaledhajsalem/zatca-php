# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.5] - 2026-07-22

### Fixed
- **Line-level discounts (BG-27):** a line's `allowanceAmount` is now emitted as a
  valid line `cac:AllowanceCharge` (`ChargeIndicator=false`, reason, amount),
  placed in the UBL-required order between `cbc:LineExtensionAmount` and
  `cac:TaxTotal`. `cbc:LineExtensionAmount` now carries the **net** line amount
  and `cac:Price/cbc:PriceAmount` the gross unit price. Removed the hardcoded
  zero-amount `cac:Price/cac:AllowanceCharge` block.
- **Document totals:** `InvoiceData::calculateTotals()` no longer double-subtracts
  line-level allowances. Line amounts are summed net; only document-level
  allowances/charges (via `addAllowance()`/`addCharge()`) adjust the totals.
  `cbc:AllowanceTotalAmount` now reports document-level allowances only, so
  `Σ(line LineExtensionAmount) == TaxExclusiveAmount` holds.
- **Simplified (B2C) buyer party:** `addBuyerInformation()` no longer emits an
  empty `cac:PartyIdentification`, an empty `cac:PostalAddress`, or a buyer tax
  scheme for simplified invoices. Empty postal sub-elements are never written,
  and a simplified invoice to an unidentified walk-in omits
  `cac:AccountingCustomerParty` entirely.

### Added
- `InvoiceLineData::setAllowanceReason()` / `getAllowanceReason()` (default
  `discount`) for the line discount reason.
- `docs/allowances-and-buyer.md` documenting line discounts and B2B/B2C buyer
  handling with worked PHP and XML examples.

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
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-09-28

### Added
- **Validation:** `Validation\InvoiceValidator` and `InvoiceData::validate()`
  check the invoice against the XML Implementation Standard business rules
  (seller/buyer identification and address, VAT number formats, dates and
  times, codes, credit/debit note references, line values, flag combinations
  and more) and return every issue with severity, rule ID and field.
  `ZatcaManager` option `'validate' => true` throws `ZatcaValidationException`
  on errors before signing or sending.
- **Transaction flags (KSA-2):** `thirdParty()`, `nominal()`, `export()`,
  `summary()`, `selfBilled()` and matching `is*()` checks. `generateXml()`
  rejects forbidden combinations (BR-KSA-07/31/46/71/72).
- **Supply dates:** `setSupplyDate()` (KSA-5, defaults to the issue date) and
  `setSupplyEndDate()` (KSA-24, `cbc:LatestDeliveryDate`), validated per BR-KSA-36.
- **Prepayment adjustment (§9.5, BR-KSA-73 to 82):** `addPrepayment()` adds a
  zero-value line referencing the 386 invoice with its VAT breakdown;
  `PrepaidAmount` and `PayableAmount` follow BR-KSA-80 and BR-CO-16.
- Invoice note (BT-22): `setNote()`.
- Rounding amount (BT-114): `setPayableRoundingAmount()`.
- `setAdditionalStreetName()` on seller and buyer.
- Item fields: `setDescription()` and `setItemCode()` are now written, plus
  `setBuyersItemCode()` and `setStandardItemCode()`.
- Price details: `setPriceDiscount()` (BT-147, with gross price BT-148) and
  `setBaseQuantity()` (BT-149).
- `InvoiceData::getFileName()`: file name per §14.
- `InvoiceData::FIRST_INVOICE_HASH` constant.
- `Support\Money`: half-up rounding and lossless decimal formatting.
- Line charge reason: `InvoiceLineData::setChargeReason()` / `setChargeReasonCode()`,
  and `setAllowanceReasonCode()` for line discounts.
- `reason_code` key for `addAllowance()` / `addCharge()`; `channel_code` and
  `payment_id` keys for `addPaymentMeans()`.
- `InvoiceData::setTaxExchangeRate()` and `getTaxTotalAmountInTaxCurrency()`
  for invoices not in SAR.
- **Zero-rated, exempt and out-of-scope items (#12):**
  `InvoiceLineData::setTaxCategory($category, $reasonCode, $reasonText)` sets a
  line's VAT category (`S`, `Z`, `E`, `O`) with a ZATCA exemption reason code
  (e.g. `VATEX-SA-32`). The reason text defaults to ZATCA's official wording;
  `VATEX-SA-OOS` requires your own text. `Z`/`E`/`O` lines are always 0%.
  Invalid category/reason combinations throw `InvalidArgumentException`.
- `Support\TaxCategory` with the category constants and the exemption reason
  code list.
- `InvoiceData::getTaxSubtotals()`: the VAT breakdown (BG-23), one entry per
  category, rate and exemption reason.
- `InvoiceData::getLineExtensionAmount()`: sum of line net amounts (BT-106).
- `tax_category` / `tax_percent` keys for `addAllowance()` / `addCharge()`
  (default: the first line's category and rate).

### Fixed
- **Rounding (§9.3, §10):** line net amount, line VAT, VAT per category and all
  document totals are now rounded half-up to 2 decimals before being summed,
  so printed totals add up (BR-CO-10/13/15/17, BR-KSA-51). Previously, e.g.
  3 × 3.335 printed as 10.00 while totals used 10.005.
- **Line charges (BG-28):** now written as a line `cac:AllowanceCharge`
  (`ChargeIndicator=true`) with reason and reason code (BR-KSA-20/22).
  Previously they were counted in the line amount but missing from the XML.
- **Document charges:** `reason_code` is written and required (BR-KSA-19/21).
- **Allowance/charge base amount and percentage:** must be provided together
  and match the amount (BR-KSA-EN16931-03/04/05, BR-KSA-DEC-01).
- **Unit price and quantity:** no longer rounded to 2 decimals (§7.3).
- **Unit code:** `setUnitCode()` is now used; it was hardcoded to `PCE`.
- **Empty elements (BR-KSA-F-03):** billing reference `UUID`, seller address
  fields and party identification, `PaymentDueDate`, document reference type,
  and allowance reasons are no longer written empty; the empty
  `PaymentChannelCode`/`PaymentID` pair is gone.
- **PaymentMeans element order** now follows UBL (`PaymentChannelCode` before
  `InstructionNote`).
- **Invoice flags (KSA-2):** `isSimplified()` / `isStandard()` check only the
  subtype, so flagged invoices (e.g. export `0100100`) are still cleared, and
  `simplified()` / `standard()` keep the flag positions.
- **Foreign-currency invoices (BR-KSA-CL-02):** the VAT breakdown now uses the
  invoice currency; only the VAT total in accounting currency (BT-111) is in
  SAR, converted with the exchange rate.
- **First-invoice PIH (#13):** the previous invoice hash of the first invoice
  in a chain is now `InvoiceData::FIRST_INVOICE_HASH`
  (`NWZlY2ViNjZm...NTdlOQ==`, the base64 of the hex SHA-256 of `"0"`, per
  BR-KSA-26) instead of `MA==` (base64 of `"0"`). This is the new default, and
  `setPreviousInvoiceHash('MA==')` is converted to it automatically.
- **VAT breakdown:** `cac:TaxTotal` now has one `cac:TaxSubtotal` per VAT
  category instead of a single hardcoded `S` / 15% subtotal. Z/E/O subtotals
  carry `cbc:TaxExemptionReasonCode` and `cbc:TaxExemptionReason`; `O` carries
  no `cbc:Percent`. Line `cac:ClassifiedTaxCategory` uses the line's category.
- **Document VAT with allowances/charges:** `taxTotalAmount` is now the sum of
  the VAT breakdown, so document-level allowances/charges reduce/increase VAT
  (BR-CO-14). Previously VAT ignored them.
- **Document-level allowances/charges XML:** allowances are emitted with
  `ChargeIndicator=false` (was `true`), amounts carry `currencyID`, a
  `cac:TaxCategory` is included, and `MultiplierFactorNumeric` / `BaseAmount`
  are only written when provided. The default zero allowance takes the first
  line's category, so an all-exempt invoice no longer contains an `S` category
  (BR-S-01).
- **Monetary totals:** `cbc:LineExtensionAmount` is now the sum of line net
  amounts (BR-CO-10) instead of the tax-exclusive total, and
  `cbc:ChargeTotalAmount` is emitted when document-level charges exist
  (BR-CO-13).
- **QR code tag 4** now holds the amount due (`PayableAmount`, BT-115)
  instead of the total with VAT. They differ when there is a prepayment or
  rounding amount; ZATCA flagged the old value with
  `invoiceTotal_QRCODE_INVALID`.
- **Walk-in buyer (regression from 1.0.5):** a simplified invoice without buyer
  details omitted `cac:AccountingCustomerParty`, which UBL requires, so ZATCA
  rejected it with `XSD_ZATCA_INVALID`. The party now contains only the
  buyer's country (default `SA`), also when no buyer is set.
- **API errors with an empty body** (e.g. 401 Unauthorized) no longer surface
  as "Failed to parse API response: Syntax error"; the exception message now
  includes the HTTP status (`ZATCA API returned HTTP 401 Unauthorized`).
- **Compliance checks:** `ZatcaAPIService::validateInvoiceCompliance()` no
  longer swallows errors and returns `[]` when warnings are allowed; failures
  (e.g. 401) now throw `ZatcaApiException`.

### Changed
- **Examples:** `certificate-generation.php` now performs the full sandbox
  onboarding (CSR, compliance and production certificate) and writes
  `storage/certificate.pem`, `private.pem` and `secret.txt`; the other examples
  read the secret from `storage/secret.txt`. `invoice-types.php` uses valid
  parties, chains ICV/PIH, and adds a final invoice with a prepayment deduction.
  The sandbox API secret is now stored in `storage/secret.txt`.
- VAT rates in the breakdown are written as `15.00` instead of `15`.
- `setTransactionCode()` now sets the KSA-2 code (it previously had no effect),
  and `setInvoiceTypeName()` throws for a code that isn't `NNPNESB`.
- `InvoiceLineData` default unit code is `PCE` (what the XML always used).
- **Breaking:** `addCharge()` throws without `reason` and `reason_code`, and
  `generateXml()` throws for a line charge without them or for a non-SAR
  invoice without `setTaxExchangeRate()`. ZATCA rejects these invoices anyway.
- README: environment note for OTP/CSIDs (simulation vs sandbox), compliance
  certificate saved separately from the production certificate, and a
  zero-rated/exempt/out-of-scope section.

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
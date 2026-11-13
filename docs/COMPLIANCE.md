# ZATCA Coverage

What this package covers of ZATCA's Phase 2 (integration) requirements, how each rule is handled, and what is not covered. Last reviewed for version 1.2.0.

> Passing these checks is evidence, not certification. Regulatory compliance is established by ZATCA's own compliance checks for your taxpayer and solution, and remains the taxpayer's responsibility.

## Baseline

| Source | Version |
|---|---|
| Electronic Invoice XML Implementation Standard | v1.2, 2023-05-19 |
| Security Features Implementation Standards | v1.2, 2023-05-19 |
| ZATCA SDK release notes (rules added after the standard) | up to SDK 3.4.8 |
| Sandbox API specification | 2.1.x (developer portal) |

The published standard stops at May 2023. ZATCA has since added and amended rules through SDK releases only, without updating that document. Those changes are listed under [Rules added after v1.2](#rules-added-after-v12).

## How coverage is verified

`tests/Fixtures/Scenarios.php` holds 63 documents covering every document type, transaction flag, VAT category and optional field the package supports.

- **Offline** (`composer test`): every scenario passes the local validator with no issue, produces XML without empty elements and with consistent totals, and signs to a hash that survives the round trip ZATCA performs.
- **Against ZATCA** (`composer test-sandbox`): every scenario is signed with a freshly issued compliance certificate and submitted to the developer-portal validator, which runs the same XSD, EN 16931 and KSA rules as the SDK. Each must return `PASS` with **no warnings**. The suite then obtains a production certificate and reports and clears a document.

On 2026-11-13 all 63 scenarios passed without warnings, on Guzzle 7.10 and Guzzle 8.5.

Only the developer portal was used. The simulation and production environments require a taxpayer's portal access and were not exercised.

## Documents

| | Standard (cleared) | Simplified (reported) |
|---|---|---|
| Tax invoice (388) | yes | yes |
| Credit note (381) | yes | yes |
| Debit note (383) | yes | yes |
| Prepayment invoice (386) | yes | yes |

| Transaction flag (KSA-2) | Standard | Simplified |
|---|---|---|
| Third party | yes | yes |
| Nominal supply | yes | yes |
| Export | yes | yes (allowed since SDK 3.4.6) |
| Summary | yes | yes |
| Self-billed | yes | not allowed by ZATCA |

## Content

| Feature | Supported |
|---|---|
| VAT categories S, Z, E, O with all 19 exemption reason codes | yes |
| Standard rates 5% and 15%, mixed in one invoice | yes |
| Document-level allowances and charges, amount or percentage, per VAT category | yes |
| Line-level allowances and charges, several per line, amount or percentage | yes |
| Price discount (BT-147) and price base quantity (BT-149) | yes |
| Prepayment deduction lines, with exemption reason (KSA-41/42) | yes |
| Foreign-currency invoices with `cac:TaxExchangeRate` and VAT total in SAR | yes |
| Payable rounding amount (BT-114) | yes |
| Several payment means; payment account (BT-84); payment terms (KSA-22) | yes |
| Purchase order (BT-13), contract (BT-12), VAT point date (BT-7) | yes |
| Billing references with issue date; additional document references | yes |
| Supply date and supply end date | yes |
| Buyer contact (name, telephone, e-mail, note); company legal form (KSA-40) | yes |
| Delivery location | yes |
| Item description, seller's, buyer's and standard identifiers, unit codes | yes |
| Arabic and other UTF-8 content | yes |
| File name per §14 | yes |

## Security features

| Feature | Supported |
|---|---|
| CSR on secp256k1 with ZATCA's subject and extension fields | yes |
| Invoice hash: SHA-256 over the C14N 1.1 form without extensions, QR and signature | yes |
| XAdES enveloped signature (ECDSA-SHA256) with signed properties | yes |
| QR code, TLV tags 1-8, and tag 9 for simplified documents | yes |
| QR tag 4 is the amount due (BT-115), as required since SDK 3.3.0 | yes |
| Previous invoice hash and invoice counter fields | yes (the application stores and supplies the chain) |
| Hash self-check after signing | yes: the signer refuses a document whose final layout would not reproduce the signed hash |
| QR length limit of 1000 characters (BR-CL-KSA-14) | yes: the signer refuses a longer QR |

## API

| Operation | Method |
|---|---|
| Compliance CSID | `requestComplianceCertificate()` |
| Compliance check | `checkCompliance()` / `validateInvoiceCompliance()` |
| Production CSID | `requestProductionCertificate()` |
| Production CSID renewal | `renewProductionCertificate()` |
| Reporting | `report()` / `reportInvoice()` |
| Clearance | `clear()` / `clearInvoice()` |

`report()`, `clear()` and `checkCompliance()` return a `SubmissionResult` that keeps the HTTP status, because ZATCA encodes outcomes there:

| Status | Meaning | `getOutcome()` |
|---|---|---|
| 200 | accepted | `accepted` |
| 202 | accepted with warnings | `accepted_with_warnings` |
| 208 | clearance: hash already submitted, cleared invoice returned again | `already_accepted` |
| 409 | reporting: already reported (the body still says `NOT_REPORTED`) | `already_accepted` |
| 400 | rejected with validation errors | `rejected` |
| 303 | clearance deactivated; report instead | `clearance_disabled` |
| 401 | credentials rejected | `unauthorized` |
| 413, 429, 5xx | not received; the same document may be sent again | `not_received` |

A request that receives no response (timeout, DNS, TLS) throws `ZatcaApiException` without a `status_code` in its context: the outcome is unknown, not a rejection.

## KSA rules

How each rule is handled before a document is sent:

- **Built in** — the generator or signer always produces a conforming value.
- **Refused** — the data classes or generator throw `InvalidArgumentException`.
- **Error / Warning** — reported by `InvoiceData::validate()`, with the severity ZATCA uses.
- **ZATCA** — not checked locally; ZATCA's validator reports it.

| Rule | Subject | Handling |
|---|---|---|
| BR-KSA-03 | UUID present | Built in when the package generates it. A UUID you supply is not format-checked |
| BR-KSA-04 | Issue date not in the future | Error |
| BR-KSA-05 | Invoice type code 388, 383, 381, 386 | Error |
| BR-KSA-06 | Transaction code format | Refused |
| BR-KSA-07 | Export cannot be self-billed | Error, Refused |
| BR-KSA-08 | Seller identification scheme | Error |
| BR-KSA-09 | Seller national address | Error |
| BR-KSA-10 | Buyer address on tax invoices | Error |
| BR-KSA-11, 12, 13 | Out-of-scope rate is zero | Built in |
| BR-KSA-14 | Buyer identification scheme | Error |
| BR-KSA-15, 35 | Supply date present | Built in (defaults to the issue date) |
| BR-KSA-16 | Payment means codes | Error |
| BR-KSA-17 | Reason on credit and debit notes | Built in (generic text) and Warning when you gave none |
| BR-KSA-18 | VAT category code | Refused |
| BR-KSA-19, 21 | Document charge reason and code | Refused |
| BR-KSA-20, 22 | Line charge reason and code | Refused |
| BR-KSA-23, 24, 69, 83 | Exemption reason code and text | Refused when missing; text defaults to ZATCA's wording |
| BR-KSA-25 | Buyer name for education/healthcare on simplified | Error |
| BR-KSA-26 | Previous invoice hash format | Error |
| BR-KSA-27 | QR code present and base64 | Built in |
| BR-KSA-28, 29, 30 | Signature identifiers and method | Built in |
| BR-KSA-31 | Flags allowed on simplified | Error, Refused (self-billed only) |
| BR-KSA-33, 34 | Invoice counter present and numeric | Built in, Error |
| BR-KSA-36 | Supply end date not before supply date | Error, Refused |
| BR-KSA-37, 66 | Seller building number and postal code | Error |
| BR-KSA-39, 40 | Seller VAT number | Error |
| BR-KSA-42 | Buyer name on tax invoices | Error |
| BR-KSA-44 | Buyer VAT number format | Error |
| BR-KSA-46 | No buyer VAT number on exports | Error, Refused |
| BR-KSA-49 | National ID for education/healthcare | Error |
| BR-KSA-51, 52, 53 | Line VAT amount and amount with VAT | Built in |
| BR-KSA-56 | Billing reference on notes | Error |
| BR-KSA-60, 61 | Cryptographic stamp and previous hash present | Built in |
| BR-KSA-63, 67 | Saudi buyer address and postal code | Error |
| BR-KSA-68 | Tax currency present | Built in |
| BR-KSA-70 | Issue time format | Error |
| BR-KSA-71, 72 | Simplified summary: buyer name and supply dates | Error, Refused |
| BR-KSA-73 to 78, 82 | Prepayment line content | Built in |
| BR-KSA-79 | Prepayment VAT amount | Refused |
| BR-KSA-80 | Prepaid amount total | Built in |
| BR-KSA-81 | Buyer ID when there is no VAT number | Error |
| BR-KSA-84 | Standard rate is 5 or 15 | Error |
| BR-KSA-91, 92 | B2G: company legal form, tax point date, contract | ZATCA (see below) |
| BR-KSA-93 | Reason code on zero-rated prepayments | Warning |
| BR-KSA-94, 95, 96 | Reason code/text on other prepayments | Fields supported; ZATCA |
| BR-KSA-97 | Foreign currency: VAT totals must differ | Warning |
| BR-KSA-98 | Simplified reported within 24 hours | Warning |
| BR-KSA-99 | No empty buyer VAT element | Built in |
| BR-KSA-DEC-01 | Allowance percentage 0-100, two decimals | Refused |
| BR-KSA-DEC-02 | VAT rates 0-100, two decimals | Error, Built in |
| BR-KSA-DEC-03, 04, 06 | Two decimals on amounts | Built in |
| BR-KSA-CL-01 | Currency code | Error (format only, not checked against ISO 4217) |
| BR-KSA-CL-02 | One currency throughout | Built in |
| BR-KSA-CL-03 | MIME code | Built in |
| BR-KSA-CL-04 | Exemption reason codes | Refused |
| BR-KSA-CL-06 | Charge reason codes (UNTDID 7161) | ZATCA: any code is accepted locally |
| BR-CL-KSA-14 | QR code at most 1000 characters | Refused by the signer |
| BR-KSA-EN16931-01 | Profile ID | Built in |
| BR-KSA-EN16931-02 | Tax currency is SAR | Error |
| BR-KSA-EN16931-03, 04, 05 | Allowance/charge amount, base and percentage | Refused |
| BR-KSA-EN16931-06 | No charge at price level | Built in (never produced) |
| BR-KSA-EN16931-07 | Net price = gross price - discount | Built in, Error |
| BR-KSA-EN16931-08, 09 | Tax total structure | Built in |
| BR-KSA-EN16931-11 | Line net amount | Built in |
| BR-KSA-EN16931-12 | Price base quantity above zero | Refused |
| BR-KSA-F-01 | Date format | Error |
| BR-KSA-F-02 | Charge indicator values | Built in |
| BR-KSA-F-03 | No empty elements | Built in (asserted for every scenario) |
| BR-KSA-F-04 | No negative amounts | Error |
| BR-KSA-F-05 | Prepayment issue time | Error |
| BR-KSA-F-06 | Character limits | Warning, for the fields listed below |
| BR-KSA-F-07 to F-11 | TIN, CRN, 700, NAT, IQA identifier formats | Warning |
| BR-KSA-F-12 | No spaces in the identifier scheme | Error (the scheme is not in the list) |
| BR-KSA-F-13 | Placeholder identifiers | Warning |

The EN 16931 core rules (BR-*, BR-CO-*, BR-S/Z/E/O-*, BR-CL-*, BR-DEC-*) are satisfied by how the generator calculates and lays out the document. They are not re-validated one by one locally; the sandbox suite is what verifies them.

### Character limits checked (BR-KSA-F-06)

Limits were read from the messages of ZATCA's validator. Where a message (127) and the SDK release notes (1000) disagreed, values between the two were submitted: the 1000-character limits below are the ones ZATCA actually applies.

| Field | Limit |
|---|---|
| Invoice number (BT-1) | 1-127 |
| Invoice note (BT-22), credit/debit note reason (KSA-10), payment terms (KSA-22) | 1000 |
| Purchase order (BT-13), contract (BT-12), payment account (BT-84) | 127 |
| Seller name (BT-27), buyer name (BT-44), seller street (BT-35), buyer street (BT-50) | 1000 |
| Additional street, city, district (seller and buyer) | 127 |
| Item name (BT-153) | 3-1000 |
| Item identifiers (BT-155, BT-156, BT-157) | 127 |
| Allowance reasons (BT-97, BT-139) | 1000 |

A seller name near 1000 characters cannot be used in practice: it makes the QR code exceed 1000 characters, which ZATCA rejects.

## Rules added after v1.2

| SDK | Change | In this package since |
|---|---|---|
| 3.2.9 | BR-KSA-84: standard rate limited to 5 or 15 (an **error**) | 1.2.0 |
| 3.2.9 | Optional buyer contact fields | 1.2.0 |
| 3.3.0 | QR tag 4 reads the amount due (BT-115) | 1.1.0 |
| 3.3.3 | Exchange rate block for foreign-currency invoices | 1.2.0 |
| 3.3.4 | `VATEX-SA-DUTYFREE`; QR limit raised to 1000 | 1.2.0 |
| 3.3.5 | BR-KSA-93 to 96: prepayment exemption reason (KSA-41/42) | 1.2.0 |
| 3.3.5, 3.4.3 | BR-KSA-91/92: B2G fields | fields in 1.2.0; rule not enforced |
| 3.3.9 | BR-KSA-97: foreign-currency VAT totals | 1.2.0 |
| 3.4.0 | BR-KSA-98: 24-hour reporting warning | 1.2.0 |
| 3.4.1, 3.4.2 | No charges at price level; BR-KSA-99 | already satisfied |
| 3.4.2 | `VATEX-SA-ROYALDECREE`, `VATEX-SA-32(bis)` | 1.2.0 |
| 3.4.4, 3.4.5 | BR-KSA-F-07 to F-13: identifier formats and placeholders | 1.2.0 |
| 3.4.6 | BR-KSA-31: simplified exports allowed | 1.2.0 |

Two behaviours of ZATCA's validator had to be established by experiment because no document states them:

- **BR-KSA-F-13** fires on any identifier containing eight or more identical digits, or eight or more digits counting up or down, in every scheme including `OTH` and `PAS`. Seven do not trigger it.
- **Out-of-scope supplies** need an explicit `0.00` rate in the VAT breakdown. Without it the validator raises BR-CO-17, even though the release notes describe the rate as optional.

## Not covered

- **Local XSD and Schematron validation.** ZATCA's rules are XSLT 2.0 Schematron, which PHP cannot run natively. The local validator covers the rules in the table above; the complete rule set is exercised through the sandbox suite.
- **B2G indicator (BR-KSA-91/92).** The fields exist (`setCompanyLegalForm()`, `setTaxPointDate()`, `setContractReference()`), and ZATCA accepts documents that use them. The release notes mention "a new flag for B2G invoices" and "a new flag for continuous supply invoices" without defining them, and no combination tried made the sandbox apply these rules. How ZATCA identifies a B2G invoice is therefore **not established**.
- **Developer-portal CSR template.** Non-production CSRs use `PREZATCA-Code-Signing`, which is documented for simulation and accepted by the developer portal. No official text names the developer-portal template.
- **Arabic in CSR fields.** `CertificateBuilder` strips non-ASCII characters from the organisation fields.
- **QR time zone.** For an issue time given in UTC (`hh:mm:ssZ`) the SDK writes KSA local time into the QR code; this package writes the time as given. ZATCA's validator accepts both.
- **Verifying received documents.** There is no API to verify the signature, hash or QR code of an invoice you receive.
- **PDF/A-3 and QR images.** The package produces the XML and the QR payload, not a PDF or an image.
- **Hash chain storage and sequencing.** The package writes the counter and previous hash you give it. Keeping one gapless sequence per EGS unit, including rejected documents, is the application's job.
- **Other optional UBL elements:** tax representative, payee party, invoice period, order line references, commodity classification, item attributes, attachments.
- **Code lists not embedded:** ISO 4217 currencies, ISO 3166 countries, UN/ECE unit codes, UNTDID 5189 and 7161 reason codes. Formats are checked; membership is left to ZATCA.
- **Simulation and production API behaviour**, and any rule ZATCA applies only there.

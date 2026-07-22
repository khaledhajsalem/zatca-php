# Line Discounts (BG-27) & B2B/B2C Buyer Party

This guide documents two behaviours of the invoice builder:

1. How a **line-level discount** is expressed as a valid ZATCA/UBL line
   `cac:AllowanceCharge` (business group **BG-27**).
2. How the **buyer party** (`cac:AccountingCustomerParty`) differs between a
   **Standard (B2B)** invoice and a **Simplified (B2C)** invoice.

---

## 1. Line discounts (BG-27 allowance)

A line's `unitPrice` is the **gross** (pre-discount) unit price, and
`allowanceAmount` is the line's **total** discount. `calculateTotals()` derives
the net amounts:

```
lineExtensionAmount = quantity * unitPrice                       // gross
taxExclusiveAmount  = lineExtensionAmount - allowanceAmount      // net (+ charges)
taxAmount           = taxExclusiveAmount * (taxPercent / 100)
taxInclusiveAmount  = taxExclusiveAmount + taxAmount
```

### Building a discounted line

```php
use KhaledHajSalem\Zatca\Data\InvoiceLineData;

$line = new InvoiceLineData();
$line->setId(1)
    ->setItemName('Test Product')
    ->setQuantity(1)
    ->setUnitPrice(40.00)                     // GROSS unit price
    ->setAllowanceAmount(4.00)                // total line discount
    ->setAllowanceReason('Loyalty discount')  // optional, defaults to "discount"
    ->setTaxPercent(15.0)
    ->calculateTotals();

$invoiceData->addLine($line);
$invoiceData->calculateTotals();
```

### Resulting `cac:InvoiceLine` fragment

The discount is expressed **once**, as a line-level `cac:AllowanceCharge` placed
between `cbc:LineExtensionAmount` and `cac:TaxTotal` (the order UBL requires:
`ID`, `InvoicedQuantity`, `LineExtensionAmount`, `AllowanceCharge*`, `TaxTotal`,
`Item`, `Price`). `cbc:LineExtensionAmount` is the **net** amount (36.00), and
`cac:Price/cbc:PriceAmount` keeps the **gross** unit price (40.00).

```xml
<cac:InvoiceLine>
  <cbc:ID>1</cbc:ID>
  <cbc:InvoicedQuantity unitCode="PCE">1.00</cbc:InvoicedQuantity>
  <cbc:LineExtensionAmount currencyID="SAR">36.00</cbc:LineExtensionAmount>
  <cac:AllowanceCharge>
    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
    <cbc:AllowanceChargeReason>Loyalty discount</cbc:AllowanceChargeReason>
    <cbc:Amount currencyID="SAR">4.00</cbc:Amount>
  </cac:AllowanceCharge>
  <cac:TaxTotal>
    <cbc:TaxAmount currencyID="SAR">5.40</cbc:TaxAmount>
    <cbc:RoundingAmount currencyID="SAR">41.40</cbc:RoundingAmount>
  </cac:TaxTotal>
  <cac:Item>
    <cbc:Name>Test Product</cbc:Name>
    <cac:ClassifiedTaxCategory>
      <cbc:ID>S</cbc:ID>
      <cbc:Percent>15.00</cbc:Percent>
      <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
    </cac:ClassifiedTaxCategory>
  </cac:Item>
  <cac:Price>
    <cbc:PriceAmount currencyID="SAR">40.00</cbc:PriceAmount>
  </cac:Price>
</cac:InvoiceLine>
```

### Resulting document totals

```xml
<cac:LegalMonetaryTotal>
  <cbc:LineExtensionAmount currencyID="SAR">36.00</cbc:LineExtensionAmount>
  <cbc:TaxExclusiveAmount currencyID="SAR">36.00</cbc:TaxExclusiveAmount>
  <cbc:TaxInclusiveAmount currencyID="SAR">41.40</cbc:TaxInclusiveAmount>
  <cbc:AllowanceTotalAmount currencyID="SAR">0.00</cbc:AllowanceTotalAmount>
  <cbc:PrepaidAmount currencyID="SAR">0.00</cbc:PrepaidAmount>
  <cbc:PayableAmount currencyID="SAR">41.40</cbc:PayableAmount>
</cac:LegalMonetaryTotal>
```

> **Invariant:** `Σ(line cbc:LineExtensionAmount) == document cbc:TaxExclusiveAmount`.
> Line discounts are subtracted **once**, on the line. Only document-level
> allowances/charges added with `addAllowance()` / `addCharge()` further adjust
> the document totals — `cbc:AllowanceTotalAmount` reports document-level
> allowances only (here `0.00`), never line allowances.

---

## 2. Standard (B2B) vs Simplified (B2C) buyer

The buyer party is emitted differently depending on the invoice type. Use
`$data->standard()` for Standard (B2B, `0100000`) and `$data->simplified()` for
Simplified (B2C, `0200000`).

### B2B — full buyer party

```php
use KhaledHajSalem\Zatca\Data\BuyerData;

$invoiceData->standard();

$buyer = new BuyerData();
$buyer->setRegistrationName('Customer Company')
    ->setVatNumber('987654321098765')
    ->setPartyIdentification('987654321098765')
    ->setPartyIdentificationId('TIN')
    ->setStreetName('Customer Street')
    ->setBuildingNumber('1234')
    ->setCityName('Jeddah')
    ->setPostalZone('23456')
    ->setCountryCode('SA');

$invoiceData->setBuyer($buyer);
```

Produces the full customer party:

```xml
<cac:AccountingCustomerParty>
  <cac:Party>
    <cac:PartyIdentification>
      <cbc:ID schemeID="TIN">987654321098765</cbc:ID>
    </cac:PartyIdentification>
    <cac:PostalAddress>
      <cbc:StreetName>Customer Street</cbc:StreetName>
      <cbc:BuildingNumber>1234</cbc:BuildingNumber>
      <cbc:CityName>Jeddah</cbc:CityName>
      <cbc:PostalZone>23456</cbc:PostalZone>
      <cbc:CountrySubentity>Jeddah</cbc:CountrySubentity>
      <cac:Country><cbc:IdentificationCode>SA</cbc:IdentificationCode></cac:Country>
    </cac:PostalAddress>
    <cac:PartyTaxScheme>
      <cbc:CompanyID>987654321098765</cbc:CompanyID>
      <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
    </cac:PartyTaxScheme>
    <cac:PartyLegalEntity>
      <cbc:RegistrationName>Customer Company</cbc:RegistrationName>
    </cac:PartyLegalEntity>
  </cac:Party>
</cac:AccountingCustomerParty>
```

> Only postal sub-elements that have a value are emitted — empty
> `cbc:StreetName`, `cbc:BuildingNumber`, etc. are never written.

### B2C — walk-in buyer

For a simplified invoice to an unidentified walk-in (no VAT, no identification,
no address, no name), the buyer party is **omitted entirely**:

```php
$invoiceData->simplified();

$buyer = new BuyerData(); // nothing set
$invoiceData->setBuyer($buyer);
```

The generated XML contains **no** `cac:AccountingCustomerParty` element at all.

If only a name is provided, the party contains just the legal entity:

```php
$invoiceData->simplified();

$buyer = new BuyerData();
$buyer->setRegistrationName('Walk-in Customer');
$invoiceData->setBuyer($buyer);
```

```xml
<cac:AccountingCustomerParty>
  <cac:Party>
    <cac:PartyLegalEntity>
      <cbc:RegistrationName>Walk-in Customer</cbc:RegistrationName>
    </cac:PartyLegalEntity>
  </cac:Party>
</cac:AccountingCustomerParty>
```

> For **simplified** invoices the package no longer emits an empty
> `cac:PartyIdentification`, an empty `cac:PostalAddress`, or a buyer
> `cac:PartyTaxScheme`. The buyer tax scheme is emitted (for either invoice type)
> only when `setVatNumber()` is non-empty.

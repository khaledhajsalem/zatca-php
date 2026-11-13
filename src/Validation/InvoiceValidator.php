<?php

namespace KhaledHajSalem\Zatca\Validation;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use KhaledHajSalem\Zatca\Data\BuyerData;
use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Data\InvoiceLineData;
use KhaledHajSalem\Zatca\Data\SellerData;
use KhaledHajSalem\Zatca\Support\TaxCategory;

/**
 * Checks invoice data against the ZATCA Electronic Invoice XML Implementation
 * Standard (v1.2), plus the rules ZATCA added afterwards up to SDK 3.4.8,
 * before the XML is generated or submitted.
 *
 * Each issue is an array with the keys:
 * - severity: "error" (ZATCA rejects the invoice) or "warning" (accepted, but
 *   likely wrong)
 * - rule: the rule ID, e.g. "BR-KSA-40"
 * - field: the data field, e.g. "seller.vat_number"
 * - message: a human-readable description
 *
 * Character limits (BR-KSA-F-06), identifier formats (BR-KSA-F-07 to F-13)
 * and late reporting (BR-KSA-98) are reported as warnings, as ZATCA does.
 */
class InvoiceValidator
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    /** Seller identification schemes (BR-KSA-08). */
    public const SELLER_ID_SCHEMES = ['CRN', 'MOM', 'MLS', '700', 'SAG', 'OTH'];

    /** Buyer identification schemes (BR-KSA-14). */
    public const BUYER_ID_SCHEMES = ['TIN', 'CRN', 'MOM', 'MLS', '700', 'SAG', 'NAT', 'GCC', 'IQA', 'PAS', 'OTH'];

    /** Invoice type codes (§11.2.1, BR-KSA-05): tax invoice, debit note, credit note, prepayment. */
    public const INVOICE_TYPE_CODES = ['388', '383', '381', '386'];

    /** Payment means codes (§11.2.5, BR-KSA-16). */
    public const PAYMENT_MEANS_CODES = ['10', '30', '42', '48', '1'];

    /**
     * Identifier formats per scheme (BR-KSA-F-07 to F-11): rule, pattern,
     * description. Schemes not listed here have no format rule.
     */
    private const ID_FORMATS = [
        'TIN' => ['BR-KSA-F-07', '/^3[0-9]{9}$/', '10 digits starting with 3'],
        'CRN' => ['BR-KSA-F-08', '/^[0-9]{10}$/', '10 digits'],
        '700' => ['BR-KSA-F-09', '/^7[0-9]{9}$/', '10 digits starting with 7'],
        'NAT' => ['BR-KSA-F-10', '/^1[0-9]{9}$/', '10 digits starting with 1'],
        'IQA' => ['BR-KSA-F-11', '/^2[0-9]{9}$/', '10 digits starting with 2'],
    ];

    private DateTimeInterface $now;
    private array $issues = [];

    /**
     * @param DateTimeInterface|null $now Current time; defaults to now in Riyadh (AST).
     */
    public function __construct(?DateTimeInterface $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));
    }

    /**
     * Validate the invoice and return all issues found (empty when valid).
     */
    public function validate(InvoiceData $invoice): array
    {
        $this->issues = [];

        $this->validateHeader($invoice);
        $this->validateSeller($invoice->getSeller());
        $this->validateBuyer($invoice);
        $this->validateReferences($invoice);
        $this->validatePaymentMeans($invoice);
        $this->validateLines($invoice);
        $this->validateAllowancesAndCharges($invoice);
        $this->validateTransaction($invoice);

        return $this->issues;
    }

    /**
     * Only the issues with severity "error".
     */
    public static function errors(array $issues): array
    {
        return array_values(array_filter($issues, fn(array $issue) => $issue['severity'] === self::ERROR));
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    private function validateHeader(InvoiceData $invoice): void
    {
        $this->required($invoice->getInvoiceNumber(), 'BR-02', 'invoice_number', 'Invoice number is required.');

        if (!in_array($invoice->getInvoiceTypeCode(), self::INVOICE_TYPE_CODES, true)) {
            $this->error('BR-KSA-05', 'invoice_type_code', "Invoice type code '{$invoice->getInvoiceTypeCode()}' is not allowed. Use one of: " . implode(', ', self::INVOICE_TYPE_CODES) . '.');
        }

        $issueDate = $invoice->getIssueDate();
        if ($this->required($issueDate, 'BR-03', 'issue_date', 'Issue date is required.')
            && $this->date($issueDate, 'issue_date')
            && $issueDate > $this->now->format('Y-m-d')) {
            $this->error('BR-KSA-04', 'issue_date', "Issue date $issueDate must not be in the future.");
        }

        if ($this->required($invoice->getIssueTime(), 'BR-KSA-70', 'issue_time', 'Issue time is required.')) {
            $this->time($invoice->getIssueTime(), 'BR-KSA-70', 'issue_time');
        }

        if ($invoice->getDueDate() !== '') {
            $this->date($invoice->getDueDate(), 'due_date');
        }

        foreach (['supply_date' => $invoice->getSupplyDate(), 'supply_end_date' => $invoice->getSupplyEndDate()] as $field => $date) {
            if ($date !== '') {
                $this->date($date, $field);
            }
        }

        if (!preg_match('/^[A-Z]{3}$/', $invoice->getDocumentCurrencyCode())) {
            $this->error('BR-CL-04', 'document_currency_code', 'Invoice currency must be an ISO 4217 alpha-3 code.');
        }

        if ($invoice->getTaxCurrencyCode() !== 'SAR') {
            $this->error('BR-KSA-EN16931-02', 'tax_currency_code', 'Tax currency must be SAR.');
        }

        if (!preg_match('/^[0-9]+$/', $invoice->getInvoiceCounter())) {
            $this->error('BR-KSA-34', 'invoice_counter', 'Invoice counter value (ICV) must contain only digits.');
        }

        $pih = $invoice->getPreviousInvoiceHash();
        $decoded = base64_decode($pih, true);
        if ($pih !== InvoiceData::FIRST_INVOICE_HASH && ($decoded === false || strlen($decoded) !== 32)) {
            $this->error('BR-KSA-26', 'previous_invoice_hash', 'Previous invoice hash must be a base64-encoded SHA-256 hash.');
        }

        if ($invoice->getPayableAmount() < 0) {
            $this->error('BR-KSA-F-04', 'payable_amount', 'Amount due must not be negative; check prepayments and rounding.');
        }

        if ($invoice->getTaxPointDate() !== '') {
            $this->date($invoice->getTaxPointDate(), 'tax_point_date');
        }

        $this->length($invoice->getInvoiceNumber(), 1, 127, 'BR-KSA-F-06', 'invoice_number', 'Invoice number');
        $this->length($invoice->getNote(), 0, 1000, 'BR-KSA-F-06', 'note', 'Invoice note');
        $this->length($invoice->getPurchaseOrderReference(), 0, 127, 'BR-KSA-F-06', 'purchase_order_reference', 'Purchase order reference');
        $this->length($invoice->getContractReference(), 0, 127, 'BR-KSA-F-06', 'contract_reference', 'Contract reference');

        // BR-KSA-97: in a foreign-currency invoice the VAT total in SAR
        // cannot equal the VAT total in the invoice currency.
        if ($invoice->getDocumentCurrencyCode() !== $invoice->getTaxCurrencyCode()
            && $invoice->getTaxExchangeRate() !== null
            && $invoice->getTaxTotalAmount() > 0
            && $invoice->getTaxTotalAmountInTaxCurrency() === $invoice->getTaxTotalAmount()) {
            $this->warning('BR-KSA-97', 'tax_exchange_rate', 'The VAT total in SAR equals the VAT total in the invoice currency; check the exchange rate.');
        }

        $this->lateReporting($invoice);
    }

    /**
     * BR-KSA-98: simplified documents must be reported within 24 hours of
     * being issued. ZATCA still accepts a late report, with a warning.
     */
    private function lateReporting(InvoiceData $invoice): void
    {
        if (!$invoice->isSimplified()) {
            return;
        }

        $time = $invoice->getIssueTime();
        $utc = substr($time, -1) === 'Z';
        $issued = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $invoice->getIssueDate() . ' ' . rtrim($time, 'Z'),
            new DateTimeZone($utc ? 'UTC' : 'Asia/Riyadh')
        );

        if ($issued && $this->now->getTimestamp() - $issued->getTimestamp() > 86400) {
            $this->warning('BR-KSA-98', 'issue_date', 'Simplified invoices must be reported within 24 hours of being issued; this one is older.');
        }
    }

    private function validateSeller(?SellerData $seller): void
    {
        if (!$seller) {
            $this->error('BR-06', 'seller', 'Seller information is required.');
            return;
        }

        $this->required($seller->getRegistrationName(), 'BR-06', 'seller.registration_name', 'Seller name is required.');

        $vat = $seller->getVatNumber();
        if ($this->required($vat, 'BR-KSA-39', 'seller.vat_number', 'Seller VAT number is required.')
            && !$this->isVatNumber($vat)) {
            $this->error('BR-KSA-40', 'seller.vat_number', 'Seller VAT number must be 15 digits, starting and ending with 3.');
        }

        $this->partyIdentification(
            $seller->getPartyIdentification(),
            $seller->getPartyIdentificationId(),
            self::SELLER_ID_SCHEMES,
            'BR-KSA-08',
            'seller',
            true
        );

        $address = [
            'street_name'           => $seller->getStreetName(),
            'building_number'       => $seller->getBuildingNumber(),
            'postal_zone'           => $seller->getPostalZone(),
            'city_name'             => $seller->getCityName(),
            'city_subdivision_name' => $seller->getCitySubdivisionName(),
            'country_code'          => $seller->getCountryCode(),
        ];
        foreach ($address as $field => $value) {
            $this->required($value, 'BR-KSA-09', "seller.$field", 'Seller address must contain street, building number, postal code, city, district and country.');
        }

        if ($seller->getBuildingNumber() !== '' && !preg_match('/^[0-9]{4}$/', $seller->getBuildingNumber())) {
            $this->error('BR-KSA-37', 'seller.building_number', 'Seller building number must be 4 digits.');
        }

        if ($seller->getPostalZone() !== '' && !preg_match('/^[0-9]{5}$/', $seller->getPostalZone())) {
            $this->error('BR-KSA-66', 'seller.postal_zone', 'Seller postal code must be 5 digits.');
        }

        $this->countryCode($seller->getCountryCode(), 'seller.country_code');

        // Mandatory seller fields are reported above when empty; only the
        // upper limits are checked here.
        $this->length($seller->getRegistrationName(), 0, 1000, 'BR-KSA-F-06', 'seller.registration_name', 'Seller name');
        $this->length($seller->getStreetName(), 0, 1000, 'BR-KSA-F-06', 'seller.street_name', 'Seller street');
        $this->length($seller->getAdditionalStreetName(), 0, 127, 'BR-KSA-F-06', 'seller.additional_street_name', 'Seller additional street');
        $this->length($seller->getCityName(), 0, 127, 'BR-KSA-F-06', 'seller.city_name', 'Seller city');
        $this->length($seller->getCitySubdivisionName(), 0, 127, 'BR-KSA-F-06', 'seller.city_subdivision_name', 'Seller district');
    }

    private function validateBuyer(InvoiceData $invoice): void
    {
        $buyer = $invoice->getBuyer();
        $standard = $invoice->isStandard();

        if (!$buyer) {
            if ($standard) {
                $this->error('BR-KSA-42', 'buyer', 'Buyer information is required for tax invoices and their notes.');
            }
            return;
        }

        if ($standard) {
            $this->required($buyer->getRegistrationName(), 'BR-KSA-42', 'buyer.registration_name', 'Buyer name is required for tax invoices and their notes.');
        }

        $vat = $buyer->getVatNumber();
        if ($vat !== '' && !$invoice->isExport() && !$this->isVatNumber($vat)) {
            $this->error('BR-KSA-44', 'buyer.vat_number', 'Buyer VAT number must be 15 digits, starting and ending with 3.');
        }

        if ($standard && $vat === '' && $buyer->getPartyIdentification() === '') {
            $this->error('BR-KSA-81', 'buyer.party_identification', 'Buyer ID is required for tax invoices when the buyer has no VAT number.');
        }

        $this->partyIdentification(
            $buyer->getPartyIdentification(),
            $buyer->getPartyIdentificationId(),
            self::BUYER_ID_SCHEMES,
            'BR-KSA-14',
            'buyer',
            false
        );

        if ($buyer->getCountryCode() !== '') {
            $this->countryCode($buyer->getCountryCode(), 'buyer.country_code');
        }

        if ($standard) {
            $this->buyerAddress($buyer);
        }

        $this->length($buyer->getRegistrationName(), 0, 1000, 'BR-KSA-F-06', 'buyer.registration_name', 'Buyer name');
        $this->length($buyer->getStreetName(), 0, 1000, 'BR-KSA-F-06', 'buyer.street_name', 'Buyer street');
        $this->length($buyer->getAdditionalStreetName(), 0, 127, 'BR-KSA-F-06', 'buyer.additional_street_name', 'Buyer additional street');
        $this->length($buyer->getCityName(), 0, 127, 'BR-KSA-F-06', 'buyer.city_name', 'Buyer city');
        $this->length($buyer->getCitySubdivisionName(), 0, 127, 'BR-KSA-F-06', 'buyer.city_subdivision_name', 'Buyer district');
    }

    private function buyerAddress(BuyerData $buyer): void
    {
        foreach (['street_name' => $buyer->getStreetName(), 'city_name' => $buyer->getCityName(), 'country_code' => $buyer->getCountryCode()] as $field => $value) {
            $this->required($value, 'BR-KSA-10', "buyer.$field", 'Buyer address must contain street, city and country for tax invoices.');
        }

        if ($buyer->getCountryCode() !== 'SA') {
            return;
        }

        foreach (['building_number' => $buyer->getBuildingNumber(), 'postal_zone' => $buyer->getPostalZone(), 'city_subdivision_name' => $buyer->getCitySubdivisionName()] as $field => $value) {
            $this->required($value, 'BR-KSA-63', "buyer.$field", 'Saudi buyer address must contain street, building number, postal code, city and district.');
        }

        if ($buyer->getPostalZone() !== '' && !preg_match('/^[0-9]{5}$/', $buyer->getPostalZone())) {
            $this->error('BR-KSA-67', 'buyer.postal_zone', 'Saudi buyer postal code must be 5 digits.');
        }
    }

    private function validateReferences(InvoiceData $invoice): void
    {
        foreach ($invoice->getBillingReferences() as $i => $reference) {
            if (($reference['issue_date'] ?? '') !== '') {
                $this->date((string)$reference['issue_date'], "billing_references.$i.issue_date");
            }
        }

        if (!$invoice->isCreditOrDebitNote()) {
            return;
        }

        $references = $invoice->getBillingReferences();
        if (empty($references)) {
            $this->error('BR-KSA-56', 'billing_references', 'Credit and debit notes must reference the original invoice.');
        }

        foreach ($references as $i => $reference) {
            $this->required((string)($reference['id'] ?? ''), 'BR-55', "billing_references.$i.id", 'Billing reference must contain the original invoice number.');
        }

        $hasReason = false;
        foreach ($invoice->getPaymentMeans() as $paymentMeans) {
            $hasReason = $hasReason || ($paymentMeans['instruction_note'] ?? '') !== '';
        }

        if (!$hasReason) {
            $this->warning('BR-KSA-17', 'payment_means.instruction_note', 'No reason given for the credit/debit note; a generic reason will be used. Provide the real reason in addPaymentMeans([\'instruction_note\' => ...]).');
        }
    }

    private function validatePaymentMeans(InvoiceData $invoice): void
    {
        foreach ($invoice->getPaymentMeans() as $i => $paymentMeans) {
            $code = (string)($paymentMeans['code'] ?? '10');
            if (!in_array($code, self::PAYMENT_MEANS_CODES, true)) {
                $this->error('BR-KSA-16', "payment_means.$i.code", "Payment means code '$code' is not allowed. Use one of: " . implode(', ', self::PAYMENT_MEANS_CODES) . '.');
            }

            if (($paymentMeans['due_date'] ?? '') !== '') {
                $this->date((string)$paymentMeans['due_date'], "payment_means.$i.due_date");
            }

            $this->length((string)($paymentMeans['instruction_note'] ?? ''), 0, 1000, 'BR-KSA-F-06', "payment_means.$i.instruction_note", 'Credit/debit note reason');
            $this->length((string)($paymentMeans['account_id'] ?? ''), 0, 127, 'BR-KSA-F-06', "payment_means.$i.account_id", 'Payment account identifier');
            $this->length((string)($paymentMeans['payment_terms'] ?? ''), 0, 1000, 'BR-KSA-F-06', "payment_means.$i.payment_terms", 'Payment terms');
        }
    }

    private function validateLines(InvoiceData $invoice): void
    {
        $lines = $invoice->getLines();
        if (empty($lines)) {
            $this->error('BR-16', 'lines', 'An invoice must have at least one line.');
        }

        $buyer = $invoice->getBuyer();

        foreach ($lines as $i => $line) {
            /** @var InvoiceLineData $line */
            $field = "lines.$i";

            $this->required($line->getItemName(), 'BR-25', "$field.item_name", 'Each line must have an item name.');

            $negativeAllowanceOrCharge = false;
            foreach (array_merge($line->getAllowances(), $line->getCharges()) as $allowanceCharge) {
                $negativeAllowanceOrCharge = $negativeAllowanceOrCharge || ($allowanceCharge['amount'] ?? 0) < 0;
            }

            if ($line->getQuantity() < 0 || $line->getUnitPrice() < 0 || $line->getAllowanceAmount() < 0 || $line->getChargeAmount() < 0 || $negativeAllowanceOrCharge) {
                $this->error('BR-KSA-F-04', $field, 'Line quantity, price, allowance and charge must not be negative.');
            }

            if ($line->getNetPrice() < 0) {
                $this->error('BR-KSA-EN16931-07', "$field.price_discount", 'Price discount must not exceed the unit price.');
            }

            if ($line->getTaxCategory() === TaxCategory::STANDARD && ($line->getTaxPercent() <= 0 || $line->getTaxPercent() > 100)) {
                $this->error('BR-KSA-DEC-02', "$field.tax_percent", 'Standard-rated lines need a VAT rate above 0 and up to 100; use a zero-rated category for 0%.');
            } elseif ($line->getTaxCategory() === TaxCategory::STANDARD && !TaxCategory::isStandardRate($line->getTaxPercent())) {
                $this->error('BR-KSA-84', "$field.tax_percent", 'The VAT rate of a standard-rated line must be 5 or 15.');
            }

            $this->length($line->getItemName(), 3, 1000, 'BR-KSA-F-06', "$field.item_name", 'Item name');
            $this->length($line->getItemCode(), 0, 127, 'BR-KSA-F-06', "$field.item_code", "Item seller's identifier");
            $this->length($line->getBuyersItemCode(), 0, 127, 'BR-KSA-F-06', "$field.buyers_item_code", "Item buyer's identifier");
            $this->length($line->getStandardItemCode(), 0, 127, 'BR-KSA-F-06', "$field.standard_item_code", 'Item standard identifier');

            foreach ($line->getAllowances() as $allowance) {
                $this->length((string)($allowance['reason'] ?? ''), 0, 1000, 'BR-KSA-F-06', "$field.allowance_reason", 'Line allowance reason');
            }

            $reasonCode = $line->getTaxExemptionReasonCode();
            if (in_array($reasonCode, ['VATEX-SA-EDU', 'VATEX-SA-HEA'], true)) {
                if (!$buyer || $buyer->getPartyIdentification() === '' || $buyer->getPartyIdentificationId() !== 'NAT') {
                    $this->error('BR-KSA-49', 'buyer.party_identification', "$reasonCode requires the buyer's national ID (scheme NAT).");
                }

                if ($invoice->isSimplified() && (!$buyer || $buyer->getRegistrationName() === '')) {
                    $this->error('BR-KSA-25', 'buyer.registration_name', "$reasonCode on a simplified invoice requires the buyer name.");
                }
            }
        }

        foreach ($invoice->getPrepayments() as $i => $prepayment) {
            $this->date($prepayment['issue_date'], "prepayments.$i.issue_date");
            $this->time($prepayment['issue_time'], 'BR-KSA-F-05', "prepayments.$i.issue_time");

            if ($prepayment['tax_category'] === TaxCategory::STANDARD && !TaxCategory::isStandardRate((float)$prepayment['tax_percent'])) {
                $this->error('BR-KSA-84', "prepayments.$i.tax_percent", 'The VAT rate of a standard-rated prepayment must be 5 or 15.');
            }

            if ($prepayment['tax_category'] === TaxCategory::ZERO_RATED && ($prepayment['tax_exemption_reason_code'] ?? null) === null) {
                $this->warning('BR-KSA-93', "prepayments.$i.tax_exemption_reason_code", 'A zero-rated prepayment should carry its VAT exemption reason code (KSA-41).');
            }
        }
    }

    private function validateAllowancesAndCharges(InvoiceData $invoice): void
    {
        foreach (['allowances' => $invoice->getAllowances(), 'charges' => $invoice->getCharges()] as $type => $items) {
            foreach ($items as $i => $item) {
                if (($item['amount'] ?? 0) < 0) {
                    $this->error('BR-KSA-F-04', "$type.$i.amount", 'Allowance/charge amounts must not be negative.');
                }

                $taxCategory = $invoice->getAllowanceChargeTaxCategory($item);
                if ($taxCategory['category'] === TaxCategory::STANDARD && $taxCategory['percent'] <= 0) {
                    $rule = $type === 'charges' ? 'BR-S-07' : 'BR-S-06';
                    $this->error($rule, "$type.$i.tax_percent", 'Standard-rated allowances/charges need a VAT rate above 0.');
                } elseif ($taxCategory['category'] === TaxCategory::STANDARD && !TaxCategory::isStandardRate((float)$taxCategory['percent'])) {
                    $this->error('BR-KSA-84', "$type.$i.tax_percent", 'The VAT rate of a standard-rated allowance/charge must be 5 or 15.');
                }

                $this->length((string)($item['reason'] ?? ''), 0, 1000, 'BR-KSA-F-06', "$type.$i.reason", 'Allowance/charge reason');
            }
        }
    }

    private function validateTransaction(InvoiceData $invoice): void
    {
        if ($invoice->isExport() && $invoice->isSelfBilled()) {
            $this->error('BR-KSA-07', 'transaction_code', 'Export invoices cannot be self-billed.');
        }

        // Simplified exports are accepted since ZATCA SDK 3.4.6.
        if ($invoice->isSimplified() && $invoice->isSelfBilled()) {
            $this->error('BR-KSA-31', 'transaction_code', 'Simplified invoices cannot be self-billed; they accept the third-party, nominal, export and summary flags.');
        }

        $buyer = $invoice->getBuyer();

        if ($invoice->isExport() && $buyer && $buyer->getVatNumber() !== '') {
            $this->error('BR-KSA-46', 'buyer.vat_number', 'Export invoices must not contain a buyer VAT number.');
        }

        $end = $invoice->getSupplyEndDate();
        if ($end !== '' && $end < $invoice->getSupplyDate()) {
            $this->error('BR-KSA-36', 'supply_end_date', 'Supply end date must not be before the supply date.');
        }

        if ($invoice->isSimplified() && $invoice->isSummary()) {
            if ($end === '') {
                $this->error('BR-KSA-72', 'supply_end_date', 'Simplified summary invoices require a supply end date.');
            }

            if (!$buyer || $buyer->getRegistrationName() === '') {
                $this->error('BR-KSA-71', 'buyer.registration_name', 'Simplified summary invoices require the buyer name.');
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function partyIdentification(string $id, string $scheme, array $schemes, string $rule, string $party, bool $required): void
    {
        if ($id === '') {
            if ($required) {
                $this->error($rule, "$party.party_identification", ucfirst($party) . ' identification (e.g. CRN) is required.');
            }
            return;
        }

        if (!in_array($scheme, $schemes, true)) {
            $this->error($rule, "$party.party_identification_id", ucfirst($party) . " identification scheme '$scheme' is not allowed. Use one of: " . implode(', ', $schemes) . '.');
        }

        if (!preg_match('/^[A-Za-z0-9]+$/', $id)) {
            $this->error($rule, "$party.party_identification", ucfirst($party) . ' identification must contain only letters and digits.');
        }

        // Format checks added by ZATCA SDK 3.4.4 / 3.4.5. ZATCA reports these
        // as warnings: the document is accepted, but flagged.
        if (isset(self::ID_FORMATS[$scheme]) && !preg_match(self::ID_FORMATS[$scheme][1], $id)) {
            $this->warning(
                self::ID_FORMATS[$scheme][0],
                "$party.party_identification",
                ucfirst($party) . " identification with scheme '$scheme' must be " . self::ID_FORMATS[$scheme][2] . '.'
            );
        }

        if ($this->looksLikePlaceholderId($id)) {
            $this->warning(
                'BR-KSA-F-13',
                "$party.party_identification",
                ucfirst($party) . ' identification looks like a placeholder (eight or more repeated or sequential digits).'
            );
        }
    }

    /**
     * BR-KSA-F-13: ZATCA flags identifiers that contain a run of eight or
     * more identical digits, or eight or more digits counting up or down
     * (thresholds established against the sandbox validator).
     */
    private function looksLikePlaceholderId(string $id): bool
    {
        $length = strlen($id);
        $same = $up = $down = 1;

        for ($i = 1; $i < $length; $i++) {
            $current = $id[$i];
            $previous = $id[$i - 1];

            if (!ctype_digit($current) || !ctype_digit($previous)) {
                $same = $up = $down = 1;
                continue;
            }

            $difference = (int)$current - (int)$previous;
            $same = $difference === 0 ? $same + 1 : 1;
            $up = $difference === 1 ? $up + 1 : 1;
            $down = $difference === -1 ? $down + 1 : 1;

            if ($same >= 8 || $up >= 8 || $down >= 8) {
                return true;
            }
        }

        return false;
    }

    /**
     * BR-KSA-F-06: character limits of the Data Dictionary, reported by
     * ZATCA as warnings. A minimum of 0 means the field is optional.
     */
    private function length(string $value, int $min, int $max, string $rule, string $field, string $label): void
    {
        if ($value === '' && $min === 0) {
            return;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        if ($length < $min || $length > $max) {
            $this->warning($rule, $field, $min > 0
                ? "$label must be between $min and $max characters."
                : "$label must be at most $max characters.");
        }
    }

    private function isVatNumber(string $vat): bool
    {
        return (bool)preg_match('/^3[0-9]{13}3$/', $vat);
    }

    private function countryCode(string $code, string $field): void
    {
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            $this->error('BR-CL-14', $field, 'Country code must be an ISO 3166-1 alpha-2 code (e.g. SA).');
        }
    }

    private function date(string $date, string $field): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $this->error('BR-KSA-F-01', $field, "Date '$date' must be a valid date in the format YYYY-MM-DD.");
            return false;
        }

        return true;
    }

    private function time(string $time, string $rule, string $field): void
    {
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z?$/', $time)) {
            $this->error($rule, $field, "Time '$time' must be in the format hh:mm:ss (AST) or hh:mm:ssZ (UTC).");
        }
    }

    private function required(string $value, string $rule, string $field, string $message): bool
    {
        if (trim($value) === '') {
            $this->error($rule, $field, $message);
            return false;
        }

        return true;
    }

    private function error(string $rule, string $field, string $message): void
    {
        $this->issues[] = ['severity' => self::ERROR, 'rule' => $rule, 'field' => $field, 'message' => $message];
    }

    private function warning(string $rule, string $field, string $message): void
    {
        $this->issues[] = ['severity' => self::WARNING, 'rule' => $rule, 'field' => $field, 'message' => $message];
    }
}

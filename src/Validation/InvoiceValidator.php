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
 * Standard (v1.2) business rules before the XML is generated or submitted.
 *
 * Each issue is an array with the keys:
 * - severity: "error" (ZATCA rejects the invoice) or "warning" (accepted, but
 *   likely wrong)
 * - rule: the rule ID, e.g. "BR-KSA-40"
 * - field: the data field, e.g. "seller.vat_number"
 * - message: a human-readable description
 *
 * Character limits (BR-KSA-F-06) are defined in the separate Data Dictionary
 * and are not checked here.
 */
class InvoiceValidator
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    /** Seller identification schemes (BR-KSA-08). */
    public const SELLER_ID_SCHEMES = ['CRN', 'MOM', 'MLS', '700', 'SAG', 'OTH'];

    /** Buyer identification schemes (BR-KSA-14). */
    public const BUYER_ID_SCHEMES = ['TIN', 'CRN', 'MOM', 'MLS', '700', 'SAG', 'NAT', 'GCC', 'IQA', 'PAS', 'OTH'];

    /** Payment means codes (§11.2.5, BR-KSA-16). */
    public const PAYMENT_MEANS_CODES = ['10', '30', '42', '48', '1'];

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

            if ($line->getQuantity() < 0 || $line->getUnitPrice() < 0 || $line->getAllowanceAmount() < 0 || $line->getChargeAmount() < 0) {
                $this->error('BR-KSA-F-04', $field, 'Line quantity, price, allowance and charge must not be negative.');
            }

            if ($line->getNetPrice() < 0) {
                $this->error('BR-KSA-EN16931-07', "$field.price_discount", 'Price discount must not exceed the unit price.');
            }

            if ($line->getTaxCategory() === TaxCategory::STANDARD && ($line->getTaxPercent() <= 0 || $line->getTaxPercent() > 100)) {
                $this->error('BR-KSA-DEC-02', "$field.tax_percent", 'Standard-rated lines need a VAT rate above 0 and up to 100; use a zero-rated category for 0%.');
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
                }
            }
        }
    }

    private function validateTransaction(InvoiceData $invoice): void
    {
        if ($invoice->isExport() && $invoice->isSelfBilled()) {
            $this->error('BR-KSA-07', 'transaction_code', 'Export invoices cannot be self-billed.');
        }

        if ($invoice->isSimplified() && ($invoice->isExport() || $invoice->isSelfBilled())) {
            $this->error('BR-KSA-31', 'transaction_code', 'Simplified invoices only accept the third-party, nominal and summary flags.');
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

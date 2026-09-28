<?php

namespace KhaledHajSalem\Zatca\Data;

use InvalidArgumentException;
use KhaledHajSalem\Zatca\Support\Money;
use KhaledHajSalem\Zatca\Support\TaxCategory;
use KhaledHajSalem\Zatca\Validation\InvoiceValidator;

/**
 * Data class for invoice information.
 */
class InvoiceData
{
    /**
     * PIH of the first invoice in a chain (BR-KSA-26): base64 of the hex
     * SHA-256 digest of "0", i.e. base64_encode(hash('sha256', '0')).
     */
    public const FIRST_INVOICE_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    protected string $invoiceNumber = '';
    protected string $issueDate = '';
    protected string $issueTime = '';
    protected string $dueDate = '';
    protected string $currencyCode = 'SAR';
    protected string $invoiceTypeCode = '388';
    protected string $invoiceTypeName = '0100000'; // Standard Tax Invoice by default
    protected string $documentCurrencyCode = 'SAR';
    protected string $taxCurrencyCode = 'SAR';
    protected int $lineCountNumeric = 0;
    protected string $invoiceCounter = '1'; // KSA-16: Invoice counter value
    protected string $previousInvoiceHash = self::FIRST_INVOICE_HASH; // PIH: Previous Invoice Hash
    protected float $taxTotalAmount = 0.0;
    protected float $taxExclusiveAmount = 0.0;
    protected float $taxInclusiveAmount = 0.0;
    protected float $allowanceTotalAmount = 0.0;
    protected float $chargeTotalAmount = 0.0;
    protected float $payableAmount = 0.0;
    protected ?SellerData $seller = null;
    protected ?BuyerData $buyer = null;
    protected array $lines = [];
    protected array $documentReferences = [];
    protected array $billingReferences = [];
    protected array $paymentMeans = [];
    protected array $deliveryInfo = [];
    protected array $allowances = [];
    protected array $charges = [];
    protected ?float $taxExchangeRate = null;
    protected string $note = '';
    protected string $noteLanguage = '';
    protected string $supplyDate = '';
    protected string $supplyEndDate = '';
    protected float $payableRoundingAmount = 0.0;
    protected array $prepayments = [];

    public function setInvoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setIssueDate(string $issueDate): self
    {
        $this->issueDate = $issueDate;
        return $this;
    }

    public function getIssueDate(): string
    {
        return $this->issueDate;
    }

    public function setIssueTime(string $issueTime): self
    {
        $this->issueTime = $issueTime;
        return $this;
    }

    public function getIssueTime(): string
    {
        return $this->issueTime;
    }

    public function setDueDate(string $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getDueDate(): string
    {
        return $this->dueDate;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function simplified(): self
    {
        // Only the subtype (positions 1-2) changes; KSA-2 flags are kept.
        return $this->setInvoiceTypeName('02' . substr($this->invoiceTypeName, 2));
    }

    public function standard(): self
    {
        return $this->setInvoiceTypeName('01' . substr($this->invoiceTypeName, 2));
    }

    public function taxInvoice(): self
    {
        return $this->setInvoiceTypeCode('388');
    }

    public function debitNote(): self
    {
        return $this->setInvoiceTypeCode('383');
    }

    public function creditNote(): self
    {
        return $this->setInvoiceTypeCode('381');
    }

    public function prepaymentInvoice(): self
    {
        return $this->setInvoiceTypeCode('386');
    }

    /**
     * Simplified tax invoice or associated note (KSA-2 positions 1-2 = 02),
     * regardless of the third-party/nominal/export/summary/self-billed flags.
     */
    public function isSimplified(): bool
    {
        return str_starts_with($this->invoiceTypeName, '02');
    }

    /**
     * Tax invoice or associated note (KSA-2 positions 1-2 = 01).
     */
    public function isStandard(): bool
    {
        return str_starts_with($this->invoiceTypeName, '01');
    }

    public function isDebitNote(): bool
    {
        return $this->invoiceTypeCode === '383';
    }

    public function isCreditNote(): bool
    {
        return $this->invoiceTypeCode === '381';
    }

    public function isCreditOrDebitNote(): bool
    {
        return $this->isDebitNote() || $this->isCreditNote();
    }

    public function setInvoiceTypeCode(string $invoiceTypeCode): self
    {
        $this->invoiceTypeCode = $invoiceTypeCode;
        return $this;
    }

    public function getInvoiceTypeCode(): string
    {
        return $this->invoiceTypeCode;
    }

    /**
     * Set the invoice transaction code (KSA-2, NNPNESB), e.g. "0100000".
     *
     * @throws InvalidArgumentException If the code isn't 01/02 followed by five 0/1 flags (BR-KSA-06).
     */
    public function setInvoiceTypeName(string $invoiceTypeName): self
    {
        if (!preg_match('/^0[12][01]{5}$/', $invoiceTypeName)) {
            throw new InvalidArgumentException(
                "Invalid invoice transaction code '$invoiceTypeName'. Expected NNPNESB: 01 or 02 followed by five 0/1 flags (BR-KSA-06)."
            );
        }

        $this->invoiceTypeName = $invoiceTypeName;
        return $this;
    }

    public function getInvoiceTypeName(): string
    {
        return $this->invoiceTypeName;
    }

    public function setDocumentCurrencyCode(string $documentCurrencyCode): self
    {
        $this->documentCurrencyCode = $documentCurrencyCode;
        return $this;
    }

    public function getDocumentCurrencyCode(): string
    {
        return $this->documentCurrencyCode;
    }

    public function setTaxCurrencyCode(string $taxCurrencyCode): self
    {
        $this->taxCurrencyCode = $taxCurrencyCode;
        return $this;
    }

    public function getTaxCurrencyCode(): string
    {
        return $this->taxCurrencyCode;
    }

    /**
     * Rate to convert the invoice currency into the tax currency (SAR),
     * i.e. 1 unit of DocumentCurrencyCode = $rate TaxCurrencyCode.
     * Required when the invoice currency is not the tax currency.
     */
    public function setTaxExchangeRate(float $rate): self
    {
        if ($rate <= 0) {
            throw new InvalidArgumentException('Tax exchange rate must be greater than zero.');
        }

        $this->taxExchangeRate = $rate;
        return $this;
    }

    /**
     * Rounding amount (BT-114) added to the amount due, e.g. -0.02 to round
     * the payable amount to a cash denomination.
     */
    public function setPayableRoundingAmount(float $payableRoundingAmount): self
    {
        $this->payableRoundingAmount = Money::round($payableRoundingAmount);
        return $this;
    }

    public function getPayableRoundingAmount(): float
    {
        return $this->payableRoundingAmount;
    }

    /**
     * Deduct a prepayment invoice from this invoice (§9.5, BR-KSA-73 to 80).
     * Each prepayment becomes an additional zero-value invoice line.
     *
     * Keys: id (invoice number of the 386 prepayment invoice), issue_date,
     * issue_time, taxable_amount, tax_category (default S), tax_percent
     * (default 15 for S, 0 otherwise), tax_amount (computed when omitted).
     *
     * @throws InvalidArgumentException For missing or inconsistent values.
     */
    public function addPrepayment(array $prepayment): self
    {
        foreach (['id', 'issue_date', 'issue_time', 'taxable_amount'] as $key) {
            if (($prepayment[$key] ?? '') === '') {
                throw new InvalidArgumentException("Prepayment '$key' is required (BR-KSA-73, BR-KSA-75).");
            }
        }

        $category = $prepayment['tax_category'] ?? TaxCategory::STANDARD;
        if (!in_array($category, TaxCategory::codes(), true)) {
            throw new InvalidArgumentException(
                "Invalid VAT category '$category'. Valid options: " . implode(', ', TaxCategory::codes())
            );
        }

        // Z, E and O prepayments carry a 0% rate (BR-KSA-76/77/78).
        $percent = $category === TaxCategory::STANDARD ? (float)($prepayment['tax_percent'] ?? 15.0) : 0.0;
        $taxable = Money::round((float)$prepayment['taxable_amount']);
        $expectedTax = Money::round($taxable * $percent / 100);

        if (isset($prepayment['tax_amount']) && Money::round((float)$prepayment['tax_amount']) !== $expectedTax) {
            throw new InvalidArgumentException(
                "Prepayment 'tax_amount' must equal taxable_amount × tax_percent / 100 ($expectedTax) (BR-KSA-79)."
            );
        }

        $this->prepayments[] = [
            'id'             => (string)$prepayment['id'],
            'issue_date'     => (string)$prepayment['issue_date'],
            'issue_time'     => (string)$prepayment['issue_time'],
            'tax_category'   => $category,
            'tax_percent'    => $percent,
            'taxable_amount' => $taxable,
            'tax_amount'     => $expectedTax,
        ];

        return $this;
    }

    public function getPrepayments(): array
    {
        return $this->prepayments;
    }

    /**
     * Pre-paid amount (BT-113): sum of prepayment taxable and tax amounts (BR-KSA-80).
     */
    public function getPrepaidAmount(): float
    {
        $total = 0.0;
        foreach ($this->prepayments as $prepayment) {
            $total += $prepayment['taxable_amount'] + $prepayment['tax_amount'];
        }

        return Money::round($total);
    }

    /**
     * Check the invoice against the ZATCA business rules and return all issues
     * found (empty when valid). See InvoiceValidator for the issue format.
     */
    public function validate(): array
    {
        $this->calculateTotals();

        return (new InvoiceValidator())->validate($this);
    }

    /**
     * Electronic invoice file name (§14):
     * SellerVAT_YYYYMMDDTHHMMSS_InvoiceNumber.xml
     */
    public function getFileName(): string
    {
        $vat = $this->seller ? $this->seller->getVatNumber() : '';
        $number = preg_replace('/[^A-Za-z0-9]/', '-', $this->invoiceNumber);

        return $vat . '_' . str_replace('-', '', $this->issueDate) . 'T' . str_replace(':', '', $this->issueTime) . '_' . $number . '.xml';
    }

    public function getTaxExchangeRate(): ?float
    {
        return $this->taxExchangeRate;
    }

    /**
     * Invoice total VAT amount in accounting currency (BT-111).
     *
     * @throws InvalidArgumentException If the currencies differ and no exchange rate is set.
     */
    public function getTaxTotalAmountInTaxCurrency(): float
    {
        if ($this->documentCurrencyCode === $this->taxCurrencyCode) {
            return $this->taxTotalAmount;
        }

        if ($this->taxExchangeRate === null) {
            throw new InvalidArgumentException(
                "Invoice currency {$this->documentCurrencyCode} differs from tax currency {$this->taxCurrencyCode}; "
                . 'call setTaxExchangeRate() to convert the VAT total (BT-111).'
            );
        }

        return Money::round($this->taxTotalAmount * $this->taxExchangeRate);
    }

    public function setInvoiceCounter(string $invoiceCounter): self
    {
        $this->invoiceCounter = $invoiceCounter;
        return $this;
    }

    public function getInvoiceCounter(): string
    {
        return $this->invoiceCounter;
    }

    /**
     * Alias of setInvoiceTypeName(): the KSA-2 transaction code is the
     * cbc:InvoiceTypeCode "name" attribute.
     */
    public function setTransactionCode(string $transactionCode): self
    {
        return $this->setInvoiceTypeName($transactionCode);
    }

    public function getTransactionCode(): string
    {
        return $this->invoiceTypeName;
    }

    /** KSA-2 position 3: invoice issued by a third party on behalf of the seller. */
    public function thirdParty(bool $enabled = true): self
    {
        return $this->setTransactionFlag(2, $enabled);
    }

    /** KSA-2 position 4: nominal supply (goods/services without consideration). */
    public function nominal(bool $enabled = true): self
    {
        return $this->setTransactionFlag(3, $enabled);
    }

    /** KSA-2 position 5: export to a foreign buyer. Tax invoices only (BR-KSA-31). */
    public function export(bool $enabled = true): self
    {
        return $this->setTransactionFlag(4, $enabled);
    }

    /** KSA-2 position 6: summary invoice for supplies over a period. */
    public function summary(bool $enabled = true): self
    {
        return $this->setTransactionFlag(5, $enabled);
    }

    /** KSA-2 position 7: self-billed (issued by the buyer). Tax invoices only, not with export (BR-KSA-07/31). */
    public function selfBilled(bool $enabled = true): self
    {
        return $this->setTransactionFlag(6, $enabled);
    }

    public function isThirdParty(): bool
    {
        return $this->invoiceTypeName[2] === '1';
    }

    public function isNominal(): bool
    {
        return $this->invoiceTypeName[3] === '1';
    }

    public function isExport(): bool
    {
        return $this->invoiceTypeName[4] === '1';
    }

    public function isSummary(): bool
    {
        return $this->invoiceTypeName[5] === '1';
    }

    public function isSelfBilled(): bool
    {
        return $this->invoiceTypeName[6] === '1';
    }

    private function setTransactionFlag(int $position, bool $enabled): self
    {
        $this->invoiceTypeName[$position] = $enabled ? '1' : '0';
        return $this;
    }

    /**
     * Invoice note (BT-22), e.g. a self-billing statement. Optional language ("ar", "en").
     */
    public function setNote(string $note, string $language = ''): self
    {
        $this->note = $note;
        $this->noteLanguage = $language;
        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getNoteLanguage(): string
    {
        return $this->noteLanguage;
    }

    /**
     * Supply date (KSA-5, cbc:ActualDeliveryDate). Defaults to the issue date.
     */
    public function setSupplyDate(string $supplyDate): self
    {
        $this->supplyDate = $supplyDate;
        return $this;
    }

    public function getSupplyDate(): string
    {
        return $this->supplyDate !== '' ? $this->supplyDate : $this->issueDate;
    }

    /**
     * Supply end date (KSA-24, cbc:LatestDeliveryDate). Required for simplified
     * summary invoices (BR-KSA-72); must not be before the supply date (BR-KSA-36).
     */
    public function setSupplyEndDate(string $supplyEndDate): self
    {
        $this->supplyEndDate = $supplyEndDate;
        return $this;
    }

    public function getSupplyEndDate(): string
    {
        return $this->supplyEndDate;
    }

    public function setPreviousInvoiceHash(string $previousInvoiceHash): self
    {
        // 'MA==' (base64 of "0") was documented by earlier versions as the
        // first-invoice PIH. It is never a valid hash, so map it to the value
        // ZATCA specifies.
        if ($previousInvoiceHash === 'MA==') {
            $previousInvoiceHash = self::FIRST_INVOICE_HASH;
        }

        $this->previousInvoiceHash = $previousInvoiceHash;
        return $this;
    }

    public function getPreviousInvoiceHash(): string
    {
        return $this->previousInvoiceHash;
    }

    public function setLineCountNumeric(int $lineCountNumeric): self
    {
        $this->lineCountNumeric = $lineCountNumeric;
        return $this;
    }

    public function getLineCountNumeric(): int
    {
        // Prepayment adjustment lines are invoice lines too.
        return $this->lineCountNumeric + count($this->prepayments);
    }

    public function setTaxTotalAmount(float $taxTotalAmount): self
    {
        $this->taxTotalAmount = $taxTotalAmount;
        return $this;
    }

    public function getTaxTotalAmount(): float
    {
        return $this->taxTotalAmount;
    }

    /**
     * Sum of invoice line net amounts (BT-106), before document-level
     * allowances and charges (BR-CO-10).
     */
    public function getLineExtensionAmount(): float
    {
        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += $line->getTaxExclusiveAmount();
        }

        return Money::round($total);
    }

    public function setTaxExclusiveAmount(float $taxExclusiveAmount): self
    {
        $this->taxExclusiveAmount = $taxExclusiveAmount;
        return $this;
    }

    public function getTaxExclusiveAmount(): float
    {
        return $this->taxExclusiveAmount;
    }

    public function setTaxInclusiveAmount(float $taxInclusiveAmount): self
    {
        $this->taxInclusiveAmount = $taxInclusiveAmount;
        return $this;
    }

    public function getTaxInclusiveAmount(): float
    {
        return $this->taxInclusiveAmount;
    }

    public function setAllowanceTotalAmount(float $allowanceTotalAmount): self
    {
        $this->allowanceTotalAmount = $allowanceTotalAmount;
        return $this;
    }

    public function getAllowanceTotalAmount(): float
    {
        return $this->allowanceTotalAmount;
    }

    public function setChargeTotalAmount(float $chargeTotalAmount): self
    {
        $this->chargeTotalAmount = $chargeTotalAmount;
        return $this;
    }

    public function getChargeTotalAmount(): float
    {
        return $this->chargeTotalAmount;
    }

    public function setPayableAmount(float $payableAmount): self
    {
        $this->payableAmount = $payableAmount;
        return $this;
    }

    public function getPayableAmount(): float
    {
        return $this->payableAmount;
    }

    public function setSeller(SellerData $seller): self
    {
        $this->seller = $seller;
        return $this;
    }

    public function getSeller(): ?SellerData
    {
        return $this->seller;
    }

    public function setBuyer(BuyerData $buyer): self
    {
        $this->buyer = $buyer;
        return $this;
    }

    public function getBuyer(): ?BuyerData
    {
        return $this->buyer;
    }

    public function addLine(InvoiceLineData $line): self
    {
        $this->lines[] = $line;
        $this->lineCountNumeric = count($this->lines);
        return $this;
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function addDocumentReference(array $reference): self
    {
        $this->documentReferences[] = $reference;
        return $this;
    }

    public function getDocumentReferences(): array
    {
        return $this->documentReferences;
    }

    public function addBillingReference(array $reference): self
    {
        $this->billingReferences[] = $reference;
        return $this;
    }

    public function getBillingReferences(): array
    {
        return $this->billingReferences;
    }

    public function addPaymentMeans(array $paymentMeans): self
    {
        $this->paymentMeans[] = $paymentMeans;
        return $this;
    }

    public function getPaymentMeans(): array
    {
        return $this->paymentMeans;
    }

    public function setDeliveryInfo(array $deliveryInfo): self
    {
        $this->deliveryInfo = $deliveryInfo;
        return $this;
    }

    public function getDeliveryInfo(): array
    {
        return $this->deliveryInfo;
    }

    /**
     * Add a document-level allowance (BG-20).
     *
     * Keys: amount, reason (default "discount"), reason_code (UNTDID 5189),
     * base_amount + multiplier (percentage; both or neither, amount is
     * computed when omitted), tax_category, tax_percent, id.
     *
     * @throws InvalidArgumentException For inconsistent base amount/percentage.
     */
    public function addAllowance(array $allowance): self
    {
        $allowance = $this->normalizeAllowanceCharge($allowance);
        $allowance['reason'] = ($allowance['reason'] ?? '') !== '' ? $allowance['reason'] : 'discount';

        $this->allowances[] = $allowance;
        return $this;
    }

    public function getAllowances(): array
    {
        return $this->allowances;
    }

    /**
     * Add a document-level charge (BG-21).
     *
     * Same keys as addAllowance(); `reason` and `reason_code` (UNTDID 7161,
     * e.g. "FC" = Freight service) are required (BR-KSA-19, BR-KSA-21).
     *
     * @throws InvalidArgumentException For a missing reason/reason code or
     *         inconsistent base amount/percentage.
     */
    public function addCharge(array $charge): self
    {
        if (($charge['reason_code'] ?? '') === '' || ($charge['reason'] ?? '') === '') {
            throw new InvalidArgumentException(
                "Document-level charges require 'reason' and 'reason_code' (UNTDID 7161) (BR-KSA-19, BR-KSA-21)."
            );
        }

        $this->charges[] = $this->normalizeAllowanceCharge($charge);
        return $this;
    }

    public function getCharges(): array
    {
        return $this->charges;
    }

    /**
     * Validate base amount / percentage and round the amount (BR-KSA-DEC-01,
     * BR-KSA-EN16931-03/04/05).
     */
    private function normalizeAllowanceCharge(array $allowanceCharge): array
    {
        $hasBase = isset($allowanceCharge['base_amount']);
        $hasMultiplier = isset($allowanceCharge['multiplier']);

        if ($hasBase !== $hasMultiplier) {
            throw new InvalidArgumentException(
                "Allowance/charge 'base_amount' and 'multiplier' must be provided together (BR-KSA-EN16931-04/05)."
            );
        }

        if ($hasBase) {
            $multiplier = (float)$allowanceCharge['multiplier'];

            if ($multiplier < 0 || $multiplier > 100 || Money::round($multiplier) !== $multiplier) {
                throw new InvalidArgumentException(
                    "Allowance/charge 'multiplier' must be a percentage from 0 to 100 with at most two decimals (BR-KSA-DEC-01)."
                );
            }

            $allowanceCharge['base_amount'] = Money::round((float)$allowanceCharge['base_amount']);
            $expected = Money::round($allowanceCharge['base_amount'] * $multiplier / 100);

            if (!isset($allowanceCharge['amount'])) {
                $allowanceCharge['amount'] = $expected;
            } elseif (Money::round((float)$allowanceCharge['amount']) !== $expected) {
                throw new InvalidArgumentException(
                    "Allowance/charge 'amount' must equal base_amount × multiplier / 100 ($expected) (BR-KSA-EN16931-03)."
                );
            }
        }

        $allowanceCharge['amount'] = Money::round((float)($allowanceCharge['amount'] ?? 0.0));

        return $allowanceCharge;
    }

    /**
     * Calculate totals from line items.
     */
    public function calculateTotals(): self
    {
        $lineNetTotal = 0.0;

        // Each line's taxExclusiveAmount is already NET of its own line-level
        // allowance/charge (see InvoiceLineData::calculateTotals()). Summing the
        // net amounts here means line allowances must NOT be subtracted again.
        foreach ($this->lines as $line) {
            $lineNetTotal += $line->getTaxExclusiveAmount();
        }

        // Only DOCUMENT-level allowances/charges adjust the invoice totals.
        $documentAllowance = 0.0;
        foreach ($this->allowances as $allowance) {
            $documentAllowance += $allowance['amount'] ?? 0.0;
        }

        $documentCharge = 0.0;
        foreach ($this->charges as $charge) {
            $documentCharge += $charge['amount'] ?? 0.0;
        }

        // Document VAT is the sum of the VAT breakdown (BR-CO-14), so that
        // document-level allowances/charges reduce/increase VAT correctly.
        $taxTotalAmount = 0.0;
        foreach ($this->getTaxSubtotals() as $subtotal) {
            $taxTotalAmount += $subtotal['tax_amount'];
        }

        // All document totals are rounded to two decimals (§10). Line
        // amounts are already rounded, so the sums are exact.
        $this->taxTotalAmount = Money::round($taxTotalAmount);
        // Reported document-level allowance/charge sums (BT-107 / BT-108).
        // Line-level allowances are expressed on each line, not here.
        $this->allowanceTotalAmount = Money::round($documentAllowance);
        $this->chargeTotalAmount = Money::round($documentCharge);
        $this->taxExclusiveAmount = Money::round($lineNetTotal - $documentAllowance + $documentCharge);
        $this->taxInclusiveAmount = Money::round($this->taxExclusiveAmount + $this->taxTotalAmount);
        // BR-CO-16: amount due = total with VAT - pre-paid amount + rounding amount.
        $this->payableAmount = Money::round($this->taxInclusiveAmount - $this->getPrepaidAmount() + $this->payableRoundingAmount);

        return $this;
    }

    /**
     * VAT breakdown (BG-23): one entry per VAT category, rate and exemption
     * reason, built from the lines and document-level allowances/charges.
     *
     * Each entry has the keys: category, percent, exemption_reason_code,
     * exemption_reason, taxable_amount, tax_amount.
     *
     * @throws InvalidArgumentException If an allowance/charge uses a Z, E or O
     *         category that no invoice line has.
     */
    public function getTaxSubtotals(): array
    {
        $groups = [];

        foreach ($this->lines as $line) {
            $key = $line->getTaxCategory() . '|' . $line->getTaxPercent() . '|' . $line->getTaxExemptionReasonCode();

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'category'              => $line->getTaxCategory(),
                    'percent'               => $line->getTaxPercent(),
                    'exemption_reason_code' => $line->getTaxExemptionReasonCode(),
                    'exemption_reason'      => $line->getTaxExemptionReason(),
                    'taxable_amount'        => 0.0,
                ];
            }

            $groups[$key]['taxable_amount'] += $line->getTaxExclusiveAmount();
        }

        $documentLevel = [];
        foreach ($this->allowances as $allowance) {
            $documentLevel[] = [$allowance, -1];
        }
        foreach ($this->charges as $charge) {
            $documentLevel[] = [$charge, 1];
        }

        foreach ($documentLevel as [$allowanceCharge, $sign]) {
            $taxCategory = $this->getAllowanceChargeTaxCategory($allowanceCharge);
            $key = $this->findTaxGroup($groups, $taxCategory['category'], $taxCategory['percent']);

            if ($key === null) {
                if (TaxCategory::requiresExemptionReason($taxCategory['category'])) {
                    throw new InvalidArgumentException(
                        "Document-level allowance/charge uses VAT category '{$taxCategory['category']}' but no invoice line has that category."
                    );
                }

                $key = $taxCategory['category'] . '|' . $taxCategory['percent'] . '|';
                $groups[$key] = [
                    'category'              => $taxCategory['category'],
                    'percent'               => $taxCategory['percent'],
                    'exemption_reason_code' => null,
                    'exemption_reason'      => null,
                    'taxable_amount'        => 0.0,
                ];
            }

            $groups[$key]['taxable_amount'] += $sign * ($allowanceCharge['amount'] ?? 0.0);
        }

        // VAT is rounded per category on the taxable amount, not summed from
        // rounded line VAT amounts (BR-CO-17, §10).
        foreach ($groups as &$group) {
            $group['taxable_amount'] = Money::round($group['taxable_amount']);
            $group['tax_amount'] = Money::round($group['taxable_amount'] * ($group['percent'] / 100));
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Resolve the VAT category and rate of a document-level allowance or charge.
     *
     * Uses the optional `tax_category` / `tax_percent` keys. Without
     * `tax_category`, the first line's category and rate are used, so an
     * all-exempt invoice doesn't gain a standard-rated breakdown (BR-S-01).
     *
     * @return array{category: string, percent: float}
     * @throws InvalidArgumentException For an invalid category.
     */
    public function getAllowanceChargeTaxCategory(array $allowanceCharge): array
    {
        if (!isset($allowanceCharge['tax_category'])) {
            $firstLine = $this->lines[0] ?? null;

            return $firstLine
                ? ['category' => $firstLine->getTaxCategory(), 'percent' => $firstLine->getTaxPercent()]
                : ['category' => TaxCategory::STANDARD, 'percent' => (float)($allowanceCharge['tax_percent'] ?? 15.0)];
        }

        $category = $allowanceCharge['tax_category'];

        if (!in_array($category, TaxCategory::codes(), true)) {
            throw new InvalidArgumentException(
                "Invalid VAT category '$category'. Valid options: " . implode(', ', TaxCategory::codes())
            );
        }

        $percent = $category === TaxCategory::STANDARD
            ? (float)($allowanceCharge['tax_percent'] ?? 15.0)
            : 0.0;

        return ['category' => $category, 'percent' => $percent];
    }

    /**
     * Find the first VAT breakdown group with the given category and rate.
     */
    private function findTaxGroup(array $groups, string $category, float $percent): ?string
    {
        foreach ($groups as $key => $group) {
            if ($group['category'] === $category && (float)$group['percent'] === $percent) {
                return $key;
            }
        }

        return null;
    }
}

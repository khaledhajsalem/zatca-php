<?php

namespace KhaledHajSalem\Zatca\Data;

/**
 * Data class for invoice information.
 */
class InvoiceData
{
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
    protected string $transactionCode = '0100000'; // KSA-2: Invoice transaction code (NNPNESB format)
    protected string $previousInvoiceHash = 'MA=='; // PIH: Previous Invoice Hash (base64 encoded "0")
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
        return $this->setInvoiceTypeName('0200000');
    }

    public function standard(): self
    {
        return $this->setInvoiceTypeName('0100000');
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

    public function isSimplified(): bool
    {
        return $this->invoiceTypeName === '0200000';
    }

    public function isStandard(): bool
    {
        return $this->invoiceTypeName === '0100000';
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

    public function setInvoiceTypeName(string $invoiceTypeName): self
    {
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

    public function setInvoiceCounter(string $invoiceCounter): self
    {
        $this->invoiceCounter = $invoiceCounter;
        return $this;
    }

    public function getInvoiceCounter(): string
    {
        return $this->invoiceCounter;
    }

    public function setTransactionCode(string $transactionCode): self
    {
        $this->transactionCode = $transactionCode;
        return $this;
    }

    public function getTransactionCode(): string
    {
        return $this->transactionCode;
    }

    public function setPreviousInvoiceHash(string $previousInvoiceHash): self
    {
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
        return $this->lineCountNumeric;
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

    public function addAllowance(array $allowance): self
    {
        $this->allowances[] = $allowance;
        return $this;
    }

    public function getAllowances(): array
    {
        return $this->allowances;
    }

    public function addCharge(array $charge): self
    {
        $this->charges[] = $charge;
        return $this;
    }

    public function getCharges(): array
    {
        return $this->charges;
    }

    /**
     * Calculate totals from line items.
     */
    public function calculateTotals(): self
    {
        $lineNetTotal = 0.0;
        $taxTotalAmount = 0.0;

        // Each line's taxExclusiveAmount is already NET of its own line-level
        // allowance/charge (see InvoiceLineData::calculateTotals()). Summing the
        // net amounts here means line allowances must NOT be subtracted again.
        foreach ($this->lines as $line) {
            $lineNetTotal += $line->getTaxExclusiveAmount();
            $taxTotalAmount += $line->getTaxAmount();
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

        $this->taxTotalAmount = $taxTotalAmount;
        // Reported document-level allowance/charge sums (BT-107 / BT-108).
        // Line-level allowances are expressed on each line, not here.
        $this->allowanceTotalAmount = $documentAllowance;
        $this->chargeTotalAmount = $documentCharge;
        $this->taxExclusiveAmount = $lineNetTotal - $documentAllowance + $documentCharge;
        $this->taxInclusiveAmount = $this->taxExclusiveAmount + $this->taxTotalAmount;
        $this->payableAmount = $this->taxInclusiveAmount;

        return $this;
    }
} 
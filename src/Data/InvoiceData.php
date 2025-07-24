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
        $taxExclusiveAmount = 0.0;
        $taxTotalAmount = 0.0;
        $allowanceTotalAmount = 0.0;
        $chargeTotalAmount = 0.0;

        foreach ($this->lines as $line) {
            $taxExclusiveAmount += $line->getTaxExclusiveAmount();
            $taxTotalAmount += $line->getTaxAmount();
            $allowanceTotalAmount += $line->getAllowanceAmount();
            $chargeTotalAmount += $line->getChargeAmount();
        }

        // Add document-level allowances and charges
        foreach ($this->allowances as $allowance) {
            $allowanceTotalAmount += $allowance['amount'] ?? 0.0;
        }

        foreach ($this->charges as $charge) {
            $chargeTotalAmount += $charge['amount'] ?? 0.0;
        }

        $this->taxExclusiveAmount = $taxExclusiveAmount;
        $this->taxTotalAmount = $taxTotalAmount;
        $this->allowanceTotalAmount = $allowanceTotalAmount;
        $this->chargeTotalAmount = $chargeTotalAmount;
        $this->taxInclusiveAmount = $taxExclusiveAmount + $taxTotalAmount + $chargeTotalAmount - $allowanceTotalAmount;
        $this->payableAmount = $this->taxInclusiveAmount;

        return $this;
    }
} 
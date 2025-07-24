<?php

namespace KhaledHajSalem\Zatca\Data;

/**
 * Data class for invoice line items.
 */
class InvoiceLineData
{
    protected int $id = 0;
    protected string $itemName = '';
    protected string $description = '';
    protected float $quantity = 0.0;
    protected float $unitPrice = 0.0;
    protected float $lineExtensionAmount = 0.0;
    protected float $taxAmount = 0.0;
    protected float $taxPercent = 0.0;
    protected float $taxExclusiveAmount = 0.0;
    protected float $taxInclusiveAmount = 0.0;
    protected float $allowanceAmount = 0.0;
    protected float $chargeAmount = 0.0;
    protected string $unitCode = 'EA';
    protected string $itemCode = '';
    protected array $taxCategories = [];

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setItemName(string $itemName): self
    {
        $this->itemName = $itemName;
        return $this;
    }

    public function getItemName(): string
    {
        return $this->itemName;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setLineExtensionAmount(float $lineExtensionAmount): self
    {
        $this->lineExtensionAmount = $lineExtensionAmount;
        return $this;
    }

    public function getLineExtensionAmount(): float
    {
        return $this->lineExtensionAmount;
    }

    public function setTaxAmount(float $taxAmount): self
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    public function getTaxAmount(): float
    {
        return $this->taxAmount;
    }

    public function setTaxPercent(float $taxPercent): self
    {
        $this->taxPercent = $taxPercent;
        return $this;
    }

    public function getTaxPercent(): float
    {
        return $this->taxPercent;
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

    public function setAllowanceAmount(float $allowanceAmount): self
    {
        $this->allowanceAmount = $allowanceAmount;
        return $this;
    }

    public function getAllowanceAmount(): float
    {
        return $this->allowanceAmount;
    }

    public function setChargeAmount(float $chargeAmount): self
    {
        $this->chargeAmount = $chargeAmount;
        return $this;
    }

    public function getChargeAmount(): float
    {
        return $this->chargeAmount;
    }

    public function setUnitCode(string $unitCode): self
    {
        $this->unitCode = $unitCode;
        return $this;
    }

    public function getUnitCode(): string
    {
        return $this->unitCode;
    }

    public function setItemCode(string $itemCode): self
    {
        $this->itemCode = $itemCode;
        return $this;
    }

    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    public function addTaxCategory(array $taxCategory): self
    {
        $this->taxCategories[] = $taxCategory;
        return $this;
    }

    public function getTaxCategories(): array
    {
        return $this->taxCategories;
    }

    /**
     * Calculate line totals.
     */
    public function calculateTotals(): self
    {
        $this->lineExtensionAmount = $this->quantity * $this->unitPrice;
        $this->taxExclusiveAmount = $this->lineExtensionAmount - $this->allowanceAmount + $this->chargeAmount;
        $this->taxAmount = $this->taxExclusiveAmount * ($this->taxPercent / 100);
        $this->taxInclusiveAmount = $this->taxExclusiveAmount + $this->taxAmount;

        return $this;
    }
} 
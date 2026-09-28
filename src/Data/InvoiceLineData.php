<?php

namespace KhaledHajSalem\Zatca\Data;

use KhaledHajSalem\Zatca\Support\Money;
use KhaledHajSalem\Zatca\Support\TaxCategory;

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
    protected string $allowanceReason = 'discount';
    protected string $allowanceReasonCode = '';
    protected float $chargeAmount = 0.0;
    protected string $chargeReason = '';
    protected string $chargeReasonCode = '';
    protected string $unitCode = 'PCE';
    protected string $itemCode = '';
    protected string $buyersItemCode = '';
    protected string $standardItemCode = '';
    protected string $standardItemSchemeId = '';
    protected float $priceDiscount = 0.0;
    protected ?float $baseQuantity = null;
    protected array $taxCategories = [];
    protected string $taxCategory = TaxCategory::STANDARD;
    protected ?string $taxExemptionReasonCode = null;
    protected ?string $taxExemptionReason = null;

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
        // Zero rated, exempt and out-of-scope supplies always carry a 0% rate.
        return $this->taxCategory === TaxCategory::STANDARD ? $this->taxPercent : 0.0;
    }

    /**
     * Set the VAT category of the line (S, Z, E or O).
     *
     * Z, E and O require a ZATCA exemption reason code (e.g. VATEX-SA-32);
     * the reason text defaults to ZATCA's official wording for that code,
     * except for VATEX-SA-OOS which requires free text.
     *
     * @throws \InvalidArgumentException For an invalid category or reason.
     */
    public function setTaxCategory(string $category, ?string $reasonCode = null, ?string $reasonText = null): self
    {
        [$reasonCode, $reasonText] = TaxCategory::resolve($category, $reasonCode, $reasonText);

        $this->taxCategory = $category;
        $this->taxExemptionReasonCode = $reasonCode;
        $this->taxExemptionReason = $reasonText;
        return $this;
    }

    public function getTaxCategory(): string
    {
        return $this->taxCategory;
    }

    public function getTaxExemptionReasonCode(): ?string
    {
        return $this->taxExemptionReasonCode;
    }

    public function getTaxExemptionReason(): ?string
    {
        return $this->taxExemptionReason;
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

    public function setAllowanceReason(string $allowanceReason): self
    {
        $this->allowanceReason = $allowanceReason;
        return $this;
    }

    public function getAllowanceReason(): string
    {
        return $this->allowanceReason;
    }

    /**
     * Allowance reason code (UNTDID 5189, e.g. "95" = Discount). Optional.
     */
    public function setAllowanceReasonCode(string $allowanceReasonCode): self
    {
        $this->allowanceReasonCode = $allowanceReasonCode;
        return $this;
    }

    public function getAllowanceReasonCode(): string
    {
        return $this->allowanceReasonCode;
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

    /**
     * Reason for the line charge (BT-144). Required when a charge is set (BR-KSA-22).
     */
    public function setChargeReason(string $chargeReason): self
    {
        $this->chargeReason = $chargeReason;
        return $this;
    }

    public function getChargeReason(): string
    {
        return $this->chargeReason;
    }

    /**
     * Charge reason code (BT-145, UNTDID 7161, e.g. "CG" = Cleaning).
     * Required when a charge is set (BR-KSA-20).
     */
    public function setChargeReasonCode(string $chargeReasonCode): self
    {
        $this->chargeReasonCode = $chargeReasonCode;
        return $this;
    }

    public function getChargeReasonCode(): string
    {
        return $this->chargeReasonCode;
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

    /**
     * Buyer's item identifier (BT-156).
     */
    public function setBuyersItemCode(string $buyersItemCode): self
    {
        $this->buyersItemCode = $buyersItemCode;
        return $this;
    }

    public function getBuyersItemCode(): string
    {
        return $this->buyersItemCode;
    }

    /**
     * Item standard identifier (BT-157), e.g. a GTIN with scheme "0160".
     */
    public function setStandardItemCode(string $standardItemCode, string $schemeId = ''): self
    {
        $this->standardItemCode = $standardItemCode;
        $this->standardItemSchemeId = $schemeId;
        return $this;
    }

    public function getStandardItemCode(): string
    {
        return $this->standardItemCode;
    }

    public function getStandardItemSchemeId(): string
    {
        return $this->standardItemSchemeId;
    }

    /**
     * Per-unit price discount (BT-147). setUnitPrice() is then the gross
     * price (BT-148) and the net price (BT-146) is gross - discount
     * (BR-KSA-EN16931-07).
     *
     * @throws \InvalidArgumentException For a negative discount.
     */
    public function setPriceDiscount(float $priceDiscount): self
    {
        if ($priceDiscount < 0) {
            throw new \InvalidArgumentException('Price discount must not be negative.');
        }

        $this->priceDiscount = $priceDiscount;
        return $this;
    }

    public function getPriceDiscount(): float
    {
        return $this->priceDiscount;
    }

    /**
     * Item net price (BT-146): gross unit price minus the price discount.
     */
    public function getNetPrice(): float
    {
        return $this->unitPrice - $this->priceDiscount;
    }

    /**
     * Item price base quantity (BT-149): the number of units the price
     * applies to, e.g. 12 for a price per dozen (BR-KSA-EN16931-12).
     *
     * @throws \InvalidArgumentException If not greater than zero.
     */
    public function setBaseQuantity(float $baseQuantity): self
    {
        if ($baseQuantity <= 0) {
            throw new \InvalidArgumentException('Base quantity must be greater than zero (BR-KSA-EN16931-12).');
        }

        $this->baseQuantity = $baseQuantity;
        return $this;
    }

    public function getBaseQuantity(): ?float
    {
        return $this->baseQuantity;
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
     *
     * Each part is rounded to two decimals separately, as required for the
     * invoice line net amount (XML Implementation Standard §9.3, §10).
     */
    public function calculateTotals(): self
    {
        $this->allowanceAmount = Money::round($this->allowanceAmount);
        $this->chargeAmount = Money::round($this->chargeAmount);
        // BR-KSA-EN16931-11: quantity × (net price / base quantity).
        $this->lineExtensionAmount = Money::round($this->quantity * $this->getNetPrice() / ($this->baseQuantity ?? 1.0));
        $this->taxExclusiveAmount = Money::round($this->lineExtensionAmount - $this->allowanceAmount + $this->chargeAmount);
        $this->taxAmount = Money::round($this->taxExclusiveAmount * ($this->getTaxPercent() / 100));
        $this->taxInclusiveAmount = Money::round($this->taxExclusiveAmount + $this->taxAmount);

        return $this;
    }
}

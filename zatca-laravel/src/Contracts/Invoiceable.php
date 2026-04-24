<?php

namespace KhaledHajSalem\ZatcaLaravel\Contracts;

/**
 * Implement on your Eloquent models (Order, Sale, etc.)
 * to enable automatic ZATCA invoice generation.
 */
interface Invoiceable
{
    public function toZatcaInvoice(): array;

    public function getZatcaInvoiceNumber(): string;

    public function getZatcaLines(): array;
}

<?php

namespace KhaledHajSalem\ZatcaLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;

class InvoiceCleared
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ZatcaInvoice $invoice,
        public readonly array $result,
    ) {}
}

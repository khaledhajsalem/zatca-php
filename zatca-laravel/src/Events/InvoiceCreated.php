<?php

namespace KhaledHajSalem\ZatcaLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;

class InvoiceCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ZatcaInvoice $invoice,
    ) {}
}

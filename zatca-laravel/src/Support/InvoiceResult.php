<?php

namespace KhaledHajSalem\ZatcaLaravel\Support;

use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;

class InvoiceResult
{
    public readonly string $uuid;
    public readonly ?string $hash;
    public readonly ?string $qrCode;
    public readonly ?string $xml;
    public readonly string $status;

    public function __construct(
        public readonly ZatcaInvoice $invoice,
        public readonly array $rawResult,
    ) {
        $this->uuid = $invoice->uuid;
        $this->hash = $invoice->invoice_hash;
        $this->qrCode = $invoice->qr_code;
        $this->xml = $invoice->signed_xml;
        $this->status = $invoice->status;
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['cleared', 'reported']);
    }

    public function warnings(): array
    {
        return $this->invoice->zatca_warnings ?? [];
    }

    public function errors(): array
    {
        return $this->invoice->zatca_errors ?? [];
    }

    public function toArray(): array
    {
        return [
            'uuid'     => $this->uuid,
            'hash'     => $this->hash,
            'status'   => $this->status,
            'success'  => $this->isSuccessful(),
            'warnings' => $this->warnings(),
            'errors'   => $this->errors(),
        ];
    }
}

<?php

namespace KhaledHajSalem\ZatcaLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaInvoiceChain extends Model
{
    protected $table = 'zatca_invoice_chains';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'last_icv'   => 'integer',
        'updated_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('zatca.storage.database.connection') ?? parent::getConnectionName();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZatcaDevice::class, 'device_id');
    }

    public function lastInvoice(): BelongsTo
    {
        return $this->belongsTo(ZatcaInvoice::class, 'last_invoice_id');
    }

    public function nextIcv(): int
    {
        return $this->last_icv + 1;
    }

    public function advance(string $invoiceHash, ?int $invoiceId = null): self
    {
        $this->last_icv = $this->nextIcv();
        $this->last_pih = $invoiceHash;
        $this->last_invoice_id = $invoiceId;
        $this->updated_at = now();
        $this->save();

        return $this;
    }
}

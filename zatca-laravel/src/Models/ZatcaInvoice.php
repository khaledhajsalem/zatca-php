<?php

namespace KhaledHajSalem\ZatcaLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZatcaInvoice extends Model
{
    protected $table = 'zatca_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date'           => 'date',
        'tax_exclusive_amount' => 'decimal:2',
        'tax_amount'           => 'decimal:2',
        'tax_inclusive_amount'  => 'decimal:2',
        'payable_amount'       => 'decimal:2',
        'icv'                  => 'integer',
        'zatca_warnings'       => 'array',
        'zatca_errors'         => 'array',
        'zatca_response'       => 'array',
        'submitted_at'         => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('zatca.storage.database.connection') ?? parent::getConnectionName();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZatcaDevice::class, 'device_id');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ZatcaApiLog::class, 'invoice_id');
    }

    public function isStandard(): bool
    {
        return $this->invoice_type_name === '0100000';
    }

    public function isSimplified(): bool
    {
        return $this->invoice_type_name === '0200000';
    }

    public function isCleared(): bool
    {
        return $this->status === 'cleared';
    }

    public function isReported(): bool
    {
        return $this->status === 'reported';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->zatca_warnings);
    }

    public function hasErrors(): bool
    {
        return ! empty($this->zatca_errors);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    public function scopeReported($query)
    {
        return $query->where('status', 'reported');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForDevice($query, int $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeDateBetween($query, string $from, string $to)
    {
        return $query->whereBetween('issue_date', [$from, $to]);
    }
}

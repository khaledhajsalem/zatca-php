<?php

namespace KhaledHajSalem\ZatcaLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaApiLog extends Model
{
    protected $table = 'zatca_api_logs';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'request_headers'  => 'array',
        'request_body'     => 'array',
        'response_headers' => 'array',
        'response_body'    => 'array',
        'duration_ms'      => 'integer',
        'response_status'  => 'integer',
        'retry_count'      => 'integer',
        'created_at'       => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('zatca.storage.database.connection') ?? parent::getConnectionName();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ZatcaInvoice::class, 'invoice_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZatcaDevice::class, 'device_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', 'like', "%{$endpoint}%");
    }
}

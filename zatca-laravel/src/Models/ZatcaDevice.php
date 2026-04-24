<?php

namespace KhaledHajSalem\ZatcaLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ZatcaDevice extends Model
{
    protected $table = 'zatca_devices';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return config('zatca.storage.database.connection') ?? parent::getConnectionName();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(ZatcaCertificate::class, 'device_id');
    }

    public function activeCertificate(): HasOne
    {
        return $this->hasOne(ZatcaCertificate::class, 'device_id')
            ->where('status', 'active')
            ->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ZatcaInvoice::class, 'device_id');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ZatcaApiLog::class, 'device_id');
    }

    public function chains(): HasMany
    {
        return $this->hasMany(ZatcaInvoiceChain::class, 'device_id');
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}

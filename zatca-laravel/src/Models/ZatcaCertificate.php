<?php

namespace KhaledHajSalem\ZatcaLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaCertificate extends Model
{
    protected $table = 'zatca_certificates';

    protected $guarded = ['id'];

    protected $hidden = [
        'certificate_pem',
        'private_key_pem',
        'secret',
    ];

    protected $casts = [
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('zatca.storage.database.connection') ?? parent::getConnectionName();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZatcaDevice::class, 'device_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->valid_until && $this->valid_until->diffInDays(now()) <= $days;
    }

    public function daysUntilExpiry(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }

        return (int) now()->diffInDays($this->valid_until, false);
    }
}

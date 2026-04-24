<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;

class DeviceService
{
    public function all(): Collection
    {
        return ZatcaDevice::with(['activeCertificate', 'chains'])->get();
    }

    public function find(string $uuid): ?ZatcaDevice
    {
        return ZatcaDevice::where('uuid', $uuid)->with(['certificates', 'chains'])->first();
    }

    public function create(string $name, string $serialNumber, array $attributes = []): ZatcaDevice
    {
        return ZatcaDevice::create(array_merge([
            'uuid'          => (string) Str::uuid(),
            'name'          => $name,
            'serial_number' => $serialNumber,
            'environment'   => config('zatca.environment', 'sandbox'),
            'is_active'     => true,
        ], $attributes));
    }

    public function deactivate(string $uuid): bool
    {
        return ZatcaDevice::where('uuid', $uuid)->update(['is_active' => false]) > 0;
    }

    public function activate(string $uuid): bool
    {
        return ZatcaDevice::where('uuid', $uuid)->update(['is_active' => true]) > 0;
    }
}

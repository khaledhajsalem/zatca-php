<?php

namespace KhaledHajSalem\ZatcaLaravel\Tests\Feature;

use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;
use KhaledHajSalem\ZatcaLaravel\Tests\TestCase;

class DeviceModelTest extends TestCase
{
    public function test_can_create_device(): void
    {
        $device = ZatcaDevice::create([
            'uuid'          => 'test-uuid-001',
            'name'          => 'POS-01',
            'serial_number' => 'SN-001',
            'environment'   => 'sandbox',
            'is_active'     => true,
        ]);

        $this->assertDatabaseHas('zatca_devices', [
            'uuid' => 'test-uuid-001',
            'name' => 'POS-01',
        ]);

        $this->assertTrue($device->is_active);
    }

    public function test_device_has_relationships(): void
    {
        $device = ZatcaDevice::create([
            'uuid'          => 'test-uuid-002',
            'name'          => 'POS-02',
            'serial_number' => 'SN-002',
            'environment'   => 'sandbox',
        ]);

        $this->assertCount(0, $device->certificates);
        $this->assertCount(0, $device->invoices);
        $this->assertCount(0, $device->chains);
    }

    public function test_is_production(): void
    {
        $sandbox = ZatcaDevice::create([
            'uuid'          => 'test-uuid-sb',
            'name'          => 'Sandbox',
            'serial_number' => 'SN-SB',
            'environment'   => 'sandbox',
        ]);

        $prod = ZatcaDevice::create([
            'uuid'          => 'test-uuid-prod',
            'name'          => 'Production',
            'serial_number' => 'SN-PROD',
            'environment'   => 'production',
        ]);

        $this->assertFalse($sandbox->isProduction());
        $this->assertTrue($prod->isProduction());
    }
}

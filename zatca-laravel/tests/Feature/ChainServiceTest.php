<?php

namespace KhaledHajSalem\ZatcaLaravel\Tests\Feature;

use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;
use KhaledHajSalem\ZatcaLaravel\Services\ChainService;
use KhaledHajSalem\ZatcaLaravel\Tests\TestCase;

class ChainServiceTest extends TestCase
{
    private ChainService $chainService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chainService = new ChainService();
    }

    public function test_creates_chain_for_device(): void
    {
        $device = ZatcaDevice::create([
            'uuid'          => 'chain-test-device',
            'name'          => 'Chain Test',
            'serial_number' => 'SN-CHAIN',
            'environment'   => 'sandbox',
        ]);

        $chain = $this->chainService->getOrCreateChain($device->id, '0200000');

        $this->assertEquals(0, $chain->last_icv);
        $this->assertEquals(1, $chain->nextIcv());
    }

    public function test_advances_chain(): void
    {
        $device = ZatcaDevice::create([
            'uuid'          => 'chain-advance-device',
            'name'          => 'Advance Test',
            'serial_number' => 'SN-ADV',
            'environment'   => 'sandbox',
        ]);

        $chain = $this->chainService->getOrCreateChain($device->id, '0200000');
        $chain->advance('hash-1');

        $this->assertEquals(1, $chain->last_icv);
        $this->assertEquals('hash-1', $chain->last_pih);
        $this->assertEquals(2, $chain->nextIcv());

        $chain->advance('hash-2');
        $this->assertEquals(2, $chain->last_icv);
        $this->assertEquals('hash-2', $chain->last_pih);
    }

    public function test_reset_chain(): void
    {
        $device = ZatcaDevice::create([
            'uuid'          => 'chain-reset-device',
            'name'          => 'Reset Test',
            'serial_number' => 'SN-RST',
            'environment'   => 'sandbox',
        ]);

        $chain = $this->chainService->getOrCreateChain($device->id, '0200000');
        $chain->advance('hash-1');
        $chain->advance('hash-2');

        $this->chainService->reset($device->id, '0200000');

        $chain->refresh();
        $this->assertEquals(0, $chain->last_icv);
    }
}

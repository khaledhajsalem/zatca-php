<?php

namespace KhaledHajSalem\ZatcaLaravel\Tests\Feature;

use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;
use KhaledHajSalem\ZatcaLaravel\Services\StatsService;
use KhaledHajSalem\ZatcaLaravel\Tests\TestCase;

class StatsServiceTest extends TestCase
{
    private StatsService $statsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statsService = new StatsService();
    }

    public function test_today_stats_empty(): void
    {
        $stats = $this->statsService->today();

        $this->assertEquals(0, $stats->totalInvoices);
        $this->assertEquals(0, $stats->totalCleared);
        $this->assertEquals(0, $stats->totalRejected);
        $this->assertEquals(0, $stats->totalRevenue);
    }

    public function test_today_stats_with_invoices(): void
    {
        ZatcaInvoice::create([
            'uuid'                 => 'stat-test-1',
            'invoice_number'       => 'INV-001',
            'status'               => 'cleared',
            'tax_inclusive_amount'  => 1000,
        ]);

        ZatcaInvoice::create([
            'uuid'                 => 'stat-test-2',
            'invoice_number'       => 'INV-002',
            'status'               => 'reported',
            'tax_inclusive_amount'  => 500,
        ]);

        ZatcaInvoice::create([
            'uuid'                 => 'stat-test-3',
            'invoice_number'       => 'INV-003',
            'status'               => 'rejected',
            'tax_inclusive_amount'  => 200,
        ]);

        $stats = $this->statsService->today();

        $this->assertEquals(3, $stats->totalInvoices);
        $this->assertEquals(1, $stats->totalCleared);
        $this->assertEquals(1, $stats->totalReported);
        $this->assertEquals(1, $stats->totalRejected);
        $this->assertEquals(1500.00, $stats->totalRevenue);
    }

    public function test_trend_returns_seven_days(): void
    {
        $trend = $this->statsService->trend(7);

        $this->assertCount(7, $trend);
        $this->assertArrayHasKey('date', $trend[0]);
        $this->assertArrayHasKey('cleared', $trend[0]);
        $this->assertArrayHasKey('reported', $trend[0]);
    }

    public function test_status_breakdown(): void
    {
        ZatcaInvoice::create([
            'uuid'           => 'bd-1',
            'invoice_number' => 'INV-BD1',
            'status'         => 'cleared',
        ]);
        ZatcaInvoice::create([
            'uuid'           => 'bd-2',
            'invoice_number' => 'INV-BD2',
            'status'         => 'cleared',
        ]);
        ZatcaInvoice::create([
            'uuid'           => 'bd-3',
            'invoice_number' => 'INV-BD3',
            'status'         => 'rejected',
        ]);

        $breakdown = $this->statsService->statusBreakdown();

        $this->assertEquals(2, $breakdown['cleared']);
        $this->assertEquals(1, $breakdown['rejected']);
    }
}

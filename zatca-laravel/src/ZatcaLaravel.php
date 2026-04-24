<?php

namespace KhaledHajSalem\ZatcaLaravel;

use KhaledHajSalem\ZatcaLaravel\Services\CertificateService;
use KhaledHajSalem\ZatcaLaravel\Services\ChainService;
use KhaledHajSalem\ZatcaLaravel\Services\DeviceService;
use KhaledHajSalem\ZatcaLaravel\Services\InvoiceBuilder;
use KhaledHajSalem\ZatcaLaravel\Services\StatsService;

class ZatcaLaravel
{
    public function __construct(
        private readonly ChainService $chainService,
        private readonly Services\ApiLogService $apiLogService,
    ) {}

    public function invoice(): InvoiceBuilder
    {
        return new InvoiceBuilder($this->chainService, $this->apiLogService);
    }

    public function certificate(): CertificateService
    {
        return new CertificateService();
    }

    public function chain(): ChainService
    {
        return $this->chainService;
    }

    public function device(): DeviceService
    {
        return new DeviceService();
    }

    public function stats(): StatsService
    {
        return new StatsService();
    }
}

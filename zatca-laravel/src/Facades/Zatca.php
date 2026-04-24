<?php

namespace KhaledHajSalem\ZatcaLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use KhaledHajSalem\ZatcaLaravel\ZatcaLaravel;

/**
 * @method static \KhaledHajSalem\ZatcaLaravel\Services\InvoiceBuilder invoice()
 * @method static \KhaledHajSalem\ZatcaLaravel\Services\CertificateService certificate()
 * @method static \KhaledHajSalem\ZatcaLaravel\Services\ChainService chain()
 * @method static \KhaledHajSalem\ZatcaLaravel\Services\DeviceService device()
 * @method static \KhaledHajSalem\ZatcaLaravel\Services\StatsService stats()
 *
 * @see \KhaledHajSalem\ZatcaLaravel\ZatcaLaravel
 */
class Zatca extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ZatcaLaravel::class;
    }
}

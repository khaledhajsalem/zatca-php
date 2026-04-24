<?php

namespace KhaledHajSalem\ZatcaLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaCertificate;

class CertificateExpiring
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ZatcaCertificate $certificate,
        public readonly int $daysLeft,
    ) {}
}

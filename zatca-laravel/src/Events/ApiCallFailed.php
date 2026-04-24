<?php

namespace KhaledHajSalem\ZatcaLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaApiLog;

class ApiCallFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ZatcaApiLog $apiLog,
        public readonly string $errorMessage,
    ) {}
}

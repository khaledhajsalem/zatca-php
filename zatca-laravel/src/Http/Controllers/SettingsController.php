<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaCertificate;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $cert = ZatcaCertificate::where('status', 'active')
            ->where('type', 'production')
            ->latest()
            ->first();

        return response()->json([
            'general' => [
                'environment'     => config('zatca.environment'),
                'path'            => config('zatca.path'),
                'auto_chain'      => config('zatca.auto_chain'),
                'store_xml'       => config('zatca.store_xml'),
                'api_timeout'     => config('zatca.api.timeout'),
                'retry_attempts'  => config('zatca.api.retry_attempts'),
                'retry_delay_ms'  => config('zatca.api.retry_delay_ms'),
                'verify_ssl'      => config('zatca.api.verify_ssl'),
            ],
            'seller' => config('zatca.seller'),
            'pruning' => config('zatca.pruning'),
            'notifications' => [
                'expiry_days' => config('zatca.notifications.certificate_expiry_days'),
                'channels'    => config('zatca.notifications.channels'),
                'recipients'  => config('zatca.notifications.recipients'),
            ],
            'health' => [
                'certificate' => $cert ? [
                    'valid'     => true,
                    'days_left' => $cert->daysUntilExpiry(),
                ] : [
                    'valid'     => false,
                    'days_left' => null,
                ],
                'database' => true,
            ],
        ]);
    }
}

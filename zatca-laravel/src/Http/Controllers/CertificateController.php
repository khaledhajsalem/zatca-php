<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaCertificate;

class CertificateController extends Controller
{
    public function index(): JsonResponse
    {
        $certificates = ZatcaCertificate::with('device')
            ->latest()
            ->get()
            ->map(function ($cert) {
                return [
                    'id'            => $cert->id,
                    'device_id'     => $cert->device_id,
                    'device_name'   => $cert->device->name ?? 'Unknown',
                    'type'          => $cert->type,
                    'status'        => $cert->status,
                    'serial_number' => $cert->serial_number,
                    'issuer'        => $cert->issuer,
                    'valid_from'    => $cert->valid_from?->toDateString(),
                    'valid_until'   => $cert->valid_until?->toDateString(),
                    'days_left'     => $cert->daysUntilExpiry(),
                    'is_expiring'   => $cert->isExpiringSoon(),
                    'environment'   => $cert->device->environment ?? null,
                    'created_at'    => $cert->created_at->toDateTimeString(),
                ];
            });

        return response()->json($certificates);
    }
}

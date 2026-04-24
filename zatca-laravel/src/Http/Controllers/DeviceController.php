<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;

class DeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = ZatcaDevice::with(['activeCertificate', 'chains'])
            ->withCount('invoices')
            ->get()
            ->map(function ($device) {
                $cert = $device->activeCertificate;
                $chain = $device->chains->first();

                return [
                    'id'              => $device->id,
                    'uuid'            => $device->uuid,
                    'name'            => $device->name,
                    'serial_number'   => $device->serial_number,
                    'environment'     => $device->environment,
                    'is_active'       => $device->is_active,
                    'invoices_count'  => $device->invoices_count,
                    'last_icv'        => $chain->last_icv ?? 0,
                    'cert_status'     => $cert->status ?? 'none',
                    'cert_type'       => $cert->type ?? null,
                    'cert_days_left'  => $cert?->daysUntilExpiry(),
                    'created_at'      => $device->created_at->toDateTimeString(),
                ];
            });

        return response()->json($devices);
    }
}

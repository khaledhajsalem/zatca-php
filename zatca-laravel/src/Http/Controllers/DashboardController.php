<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaCertificate;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;
use KhaledHajSalem\ZatcaLaravel\Services\StatsService;

class DashboardController extends Controller
{
    public function index()
    {
        return view('zatca::layout');
    }

    public function stats(StatsService $statsService): JsonResponse
    {
        return response()->json([
            'today'     => $statsService->today(),
            'trend'     => $statsService->trend(7),
            'breakdown' => $statsService->statusBreakdown(),
        ]);
    }

    public function recent(): JsonResponse
    {
        $invoices = ZatcaInvoice::with('device')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($invoices);
    }

    public function certificateStatus(): JsonResponse
    {
        $certificates = ZatcaCertificate::with('device')
            ->where('status', 'active')
            ->get()
            ->map(function ($cert) {
                return [
                    'device_name'   => $cert->device->name ?? 'Unknown',
                    'type'          => $cert->type,
                    'status'        => $cert->status,
                    'valid_until'   => $cert->valid_until?->toDateString(),
                    'days_left'     => $cert->daysUntilExpiry(),
                    'is_expiring'   => $cert->isExpiringSoon(),
                ];
            });

        return response()->json($certificates);
    }
}

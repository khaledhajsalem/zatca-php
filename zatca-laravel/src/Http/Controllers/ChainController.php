<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoiceChain;
use KhaledHajSalem\ZatcaLaravel\Services\ChainService;

class ChainController extends Controller
{
    public function index(Request $request, ChainService $chainService): JsonResponse
    {
        $deviceId = $request->input('device_id');
        $typeName = $request->input('type');

        $query = ZatcaInvoice::query()
            ->whereIn('status', ['cleared', 'reported', 'warning'])
            ->orderByDesc('icv');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        if ($typeName) {
            $query->where('invoice_type_name', $typeName);
        }

        $invoices = $query->limit(50)->get(['id', 'uuid', 'invoice_number', 'icv', 'pih', 'invoice_hash', 'status', 'issue_date', 'device_id', 'invoice_type_name']);

        $chain = null;
        if ($deviceId) {
            $chain = ZatcaInvoiceChain::where('device_id', $deviceId)
                ->when($typeName, fn ($q) => $q->where('invoice_type_name', $typeName))
                ->first();
        }

        $verification = null;
        if ($deviceId && $typeName) {
            $verification = $chainService->verify((int) $deviceId, $typeName);
        }

        return response()->json([
            'invoices'     => $invoices,
            'chain'        => $chain,
            'verification' => $verification,
        ]);
    }
}

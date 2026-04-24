<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ZatcaInvoice::with('device')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('invoice_type_name', $request->input('type'));
        }

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('issue_date', [$request->input('from'), $request->input('to')]);
        }

        $invoices = $query->paginate($request->input('per_page', 20));

        return response()->json($invoices);
    }

    public function show(string $uuid): JsonResponse
    {
        $invoice = ZatcaInvoice::with(['device', 'apiLogs'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json($invoice);
    }
}

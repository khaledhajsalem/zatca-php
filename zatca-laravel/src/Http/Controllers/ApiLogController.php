<?php

namespace KhaledHajSalem\ZatcaLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaApiLog;

class ApiLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ZatcaApiLog::with(['invoice', 'device'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('endpoint')) {
            $query->where('endpoint', 'like', '%' . $request->input('endpoint') . '%');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('endpoint', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json($logs);
    }

    public function show(string $uuid): JsonResponse
    {
        $log = ZatcaApiLog::with(['invoice', 'device'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json($log);
    }
}

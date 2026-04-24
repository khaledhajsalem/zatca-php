<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use Illuminate\Support\Carbon;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaApiLog;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;

class StatsService
{
    public function today(): object
    {
        return $this->forDate(Carbon::today());
    }

    public function forDate(Carbon $date): object
    {
        $invoices = ZatcaInvoice::whereDate('created_at', $date);

        $total = (clone $invoices)->count();
        $cleared = (clone $invoices)->where('status', 'cleared')->count();
        $reported = (clone $invoices)->where('status', 'reported')->count();
        $rejected = (clone $invoices)->where('status', 'rejected')->count();
        $revenue = (clone $invoices)->whereIn('status', ['cleared', 'reported'])->sum('tax_inclusive_amount');

        $avgResponseTime = ZatcaApiLog::whereDate('created_at', $date)
            ->where('status', 'success')
            ->avg('duration_ms');

        return (object) [
            'totalInvoices'      => $total,
            'totalCleared'       => $cleared,
            'totalReported'      => $reported,
            'totalRejected'      => $rejected,
            'totalRevenue'       => round($revenue, 2),
            'averageResponseTime' => round($avgResponseTime ?? 0),
        ];
    }

    public function trend(int $days = 7): array
    {
        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $stats = $this->forDate($date);
            $trend[] = [
                'date'     => $date->toDateString(),
                'label'    => $date->format('D'),
                'cleared'  => $stats->totalCleared,
                'reported' => $stats->totalReported,
                'rejected' => $stats->totalRejected,
                'total'    => $stats->totalInvoices,
            ];
        }

        return $trend;
    }

    public function statusBreakdown(): array
    {
        return ZatcaInvoice::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}

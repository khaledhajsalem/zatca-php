<?php

namespace KhaledHajSalem\ZatcaLaravel\Commands;

use Illuminate\Console\Command;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaApiLog;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;

class PruneCommand extends Command
{
    protected $signature = 'zatca:prune
        {--invoices= : Days to keep invoices (overrides config)}
        {--api-logs= : Days to keep API logs (overrides config)}';

    protected $description = 'Prune old ZATCA records';

    public function handle(): int
    {
        $invoiceDays = (int) ($this->option('invoices') ?: config('zatca.pruning.invoices_days', 365));
        $apiLogDays = (int) ($this->option('api-logs') ?: config('zatca.pruning.api_logs_days', 90));

        $this->info('Pruning ZATCA records...');

        // Prune invoices
        if ($invoiceDays > 0) {
            $deleted = ZatcaInvoice::where('created_at', '<', now()->subDays($invoiceDays))->delete();
            $this->line("  Invoices: {$deleted} records older than {$invoiceDays} days removed");
        }

        // Prune API logs
        if ($apiLogDays > 0) {
            $deleted = ZatcaApiLog::where('created_at', '<', now()->subDays($apiLogDays))->delete();
            $this->line("  API Logs: {$deleted} records older than {$apiLogDays} days removed");
        }

        $this->info('Pruning complete.');

        return self::SUCCESS;
    }
}

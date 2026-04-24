<?php

namespace KhaledHajSalem\ZatcaLaravel\Commands;

use Illuminate\Console\Command;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaCertificate;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoiceChain;

class StatusCommand extends Command
{
    protected $signature = 'zatca:status';

    protected $description = 'Show current ZATCA integration status';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=green;options=bold>ZATCA Status</>');
        $this->newLine();

        // Environment
        $env = config('zatca.environment', 'sandbox');
        $this->line("  Environment:      <fg=yellow>{$env}</>");

        // Devices
        $devices = ZatcaDevice::where('is_active', true)->get();
        $this->line("  Active Devices:   <fg=cyan>{$devices->count()}</>");

        foreach ($devices as $device) {
            $cert = $device->activeCertificate;
            $chain = ZatcaInvoiceChain::where('device_id', $device->id)->first();

            $certStatus = $cert
                ? ($cert->isExpiringSoon() ? "<fg=yellow>{$cert->daysUntilExpiry()}d left</>" : "<fg=green>Valid ({$cert->daysUntilExpiry()}d)</>")
                : '<fg=red>No certificate</>';

            $this->newLine();
            $this->line("  Device: <fg=white;options=bold>{$device->name}</> ({$device->uuid})");
            $this->line("    Serial:       {$device->serial_number}");
            $this->line("    Certificate:  {$certStatus}");
            $this->line("    Chain ICV:    " . ($chain->last_icv ?? 0));
        }

        // Recent invoices
        $this->newLine();
        $today = ZatcaInvoice::whereDate('created_at', today());
        $cleared = (clone $today)->where('status', 'cleared')->count();
        $reported = (clone $today)->where('status', 'reported')->count();
        $rejected = (clone $today)->where('status', 'rejected')->count();

        $this->line("  Today's Invoices: {$cleared} cleared, {$reported} reported, {$rejected} rejected");

        // Last invoice
        $last = ZatcaInvoice::latest()->first();
        if ($last) {
            $this->line("  Last Invoice:     {$last->invoice_number} ({$last->created_at->diffForHumans()})");
        }

        $this->newLine();

        return self::SUCCESS;
    }
}

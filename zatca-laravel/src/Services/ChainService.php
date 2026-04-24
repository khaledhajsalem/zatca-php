<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoiceChain;

class ChainService
{
    public function getOrCreateChain(int $deviceId, string $invoiceTypeName): ZatcaInvoiceChain
    {
        return ZatcaInvoiceChain::firstOrCreate(
            [
                'device_id'         => $deviceId,
                'invoice_type_name' => $invoiceTypeName,
            ],
            [
                'last_icv' => 0,
                'last_pih' => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            ]
        );
    }

    public function forDevice(string $deviceUuid): ?ZatcaInvoiceChain
    {
        return ZatcaInvoiceChain::whereHas('device', function ($q) use ($deviceUuid) {
            $q->where('uuid', $deviceUuid);
        })->first();
    }

    public function reset(int $deviceId, ?string $invoiceTypeName = null): void
    {
        $query = ZatcaInvoiceChain::where('device_id', $deviceId);

        if ($invoiceTypeName) {
            $query->where('invoice_type_name', $invoiceTypeName);
        }

        $query->update([
            'last_icv'        => 0,
            'last_pih'        => 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==',
            'last_invoice_id' => null,
            'updated_at'      => now(),
        ]);
    }

    public function verify(int $deviceId, string $invoiceTypeName): array
    {
        $chain = $this->getOrCreateChain($deviceId, $invoiceTypeName);

        $invoices = \KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice::where('device_id', $deviceId)
            ->where('invoice_type_name', $invoiceTypeName)
            ->whereIn('status', ['cleared', 'reported'])
            ->orderBy('icv')
            ->get();

        $errors = [];
        $expectedIcv = 1;

        foreach ($invoices as $invoice) {
            if ($invoice->icv !== $expectedIcv) {
                $errors[] = "ICV gap: expected {$expectedIcv}, got {$invoice->icv} at invoice {$invoice->invoice_number}";
            }
            $expectedIcv = $invoice->icv + 1;
        }

        return [
            'is_valid'      => empty($errors),
            'total_invoices' => $invoices->count(),
            'last_icv'       => $chain->last_icv,
            'errors'         => $errors,
        ];
    }
}

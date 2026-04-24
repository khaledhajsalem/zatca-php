<?php

namespace KhaledHajSalem\ZatcaLaravel\Commands;

use Illuminate\Console\Command;
use KhaledHajSalem\ZatcaLaravel\Services\CertificateService;

class GenerateCsrCommand extends Command
{
    protected $signature = 'zatca:generate-csr
        {--common-name= : Common name for the certificate}
        {--org= : Organization name}
        {--org-unit=IT : Organization unit}
        {--country=SA : Country code}
        {--org-identifier= : Organization identifier (15-digit VAT)}
        {--solution= : Solution name}
        {--model= : Device model}
        {--serial= : Device serial number}
        {--invoice-type=1100 : Invoice type (1100 for both standard and simplified)}
        {--production : Generate for production environment}';

    protected $description = 'Generate a Certificate Signing Request (CSR) for ZATCA';

    public function handle(): int
    {
        $service = new CertificateService();

        $commonName = $this->option('common-name') ?: $this->ask('Common Name (company name)', config('zatca.seller.registration_name'));
        $org = $this->option('org') ?: $this->ask('Organization', $commonName);
        $orgUnit = $this->option('org-unit') ?: $this->ask('Organization Unit', 'IT');
        $country = $this->option('country') ?: $this->ask('Country', 'SA');
        $orgId = $this->option('org-identifier') ?: $this->ask('Organization Identifier (15-digit VAT)', config('zatca.seller.vat_number'));
        $solution = $this->option('solution') ?: $this->ask('Solution Name', 'MyERP');
        $model = $this->option('model') ?: $this->ask('Device Model', 'Model1');
        $serial = $this->option('serial') ?: $this->ask('Device Serial Number', 'SN001');
        $invoiceType = (int) ($this->option('invoice-type') ?: $this->ask('Invoice Type', '1100'));
        $production = $this->option('production') ?: $this->confirm('Production environment?', false);

        $this->info('Generating CSR...');

        $result = $service
            ->commonName($commonName)
            ->orgName($org)
            ->orgUnit($orgUnit)
            ->country($country)
            ->orgIdentifier($orgId)
            ->serialNumber($solution, $model, $serial)
            ->invoiceType($invoiceType)
            ->production($production)
            ->generateCsr();

        $storagePath = storage_path('zatca');
        if (! is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        if (isset($result['csr'])) {
            file_put_contents($storagePath . '/csr.pem', $result['csr']);
            $this->info('CSR saved to: ' . $storagePath . '/csr.pem');
        }

        if (isset($result['private_key'])) {
            file_put_contents($storagePath . '/private.pem', $result['private_key']);
            $this->info('Private key saved to: ' . $storagePath . '/private.pem');
        }

        $this->newLine();
        $this->info('CSR generated successfully!');
        $this->line('Next: php artisan zatca:onboard --otp=YOUR_OTP');

        return self::SUCCESS;
    }
}

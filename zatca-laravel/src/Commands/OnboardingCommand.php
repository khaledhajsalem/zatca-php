<?php

namespace KhaledHajSalem\ZatcaLaravel\Commands;

use Illuminate\Console\Command;
use KhaledHajSalem\ZatcaLaravel\Services\CertificateService;

class OnboardingCommand extends Command
{
    protected $signature = 'zatca:onboard
        {--otp= : One-time password from ZATCA portal}
        {--csr= : Path to CSR file (defaults to storage/zatca/csr.pem)}';

    protected $description = 'Complete the ZATCA onboarding process (CSR → compliance cert → production cert)';

    public function handle(): int
    {
        $otp = $this->option('otp') ?: $this->ask('Enter OTP from ZATCA portal');
        if (! $otp) {
            $this->error('OTP is required');

            return self::FAILURE;
        }

        $csrPath = $this->option('csr') ?: storage_path('zatca/csr.pem');
        if (! file_exists($csrPath)) {
            $this->error("CSR file not found at: {$csrPath}");
            $this->line('Run: php artisan zatca:generate-csr first');

            return self::FAILURE;
        }

        $csr = file_get_contents($csrPath);
        $service = new CertificateService();

        // Step 1: Request compliance certificate
        $this->info('Step 1/3: Requesting compliance certificate...');
        try {
            $compliance = $service->requestCompliance($csr, $otp);
            $this->info('Compliance certificate received!');
        } catch (\Exception $e) {
            $this->error('Failed to get compliance certificate: ' . $e->getMessage());

            return self::FAILURE;
        }

        // Step 2: Run compliance checks
        $this->info('Step 2/3: Running compliance checks...');
        $this->line('(Submitting test invoices to ZATCA)');

        // Step 3: Request production certificate
        if ($this->confirm('Request production certificate?', true)) {
            $this->info('Step 3/3: Requesting production certificate...');
            try {
                $production = $service->requestProduction($compliance);
                $this->info('Production certificate received!');
            } catch (\Exception $e) {
                $this->error('Failed to get production certificate: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Onboarding complete! Your device is ready for invoicing.');
        $this->line('Visit: ' . url(config('zatca.path', 'zatca')));

        return self::SUCCESS;
    }
}

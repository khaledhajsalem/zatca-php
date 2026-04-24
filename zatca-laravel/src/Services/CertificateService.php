<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use KhaledHajSalem\ZatcaLaravel\Events\CertificateExpiring;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaCertificate;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;
use KhaledHajSalem\ZatcaPHP\Support\CertificateBuilder;
use KhaledHajSalem\ZatcaPHP\ZatcaManager;

class CertificateService
{
    private ?string $orgIdentifier = null;
    private ?string $commonName = null;
    private ?string $orgName = null;
    private ?string $orgUnit = null;
    private ?string $country = 'SA';
    private ?string $serialNumber = null;
    private ?string $solutionName = null;
    private ?string $model = null;
    private ?string $deviceSerial = null;
    private ?int $invoiceType = null;
    private bool $production = false;

    public function orgIdentifier(string $id): self
    {
        $this->orgIdentifier = $id;

        return $this;
    }

    public function serialNumber(string $solution, string $model, string $serial): self
    {
        $this->solutionName = $solution;
        $this->model = $model;
        $this->deviceSerial = $serial;
        $this->serialNumber = "1-{$solution}|2-{$model}|3-{$serial}";

        return $this;
    }

    public function commonName(string $name): self
    {
        $this->commonName = $name;

        return $this;
    }

    public function country(string $code): self
    {
        $this->country = $code;

        return $this;
    }

    public function orgName(string $name): self
    {
        $this->orgName = $name;

        return $this;
    }

    public function orgUnit(string $unit): self
    {
        $this->orgUnit = $unit;

        return $this;
    }

    public function invoiceType(int $type): self
    {
        $this->invoiceType = $type;

        return $this;
    }

    public function production(bool $prod = true): self
    {
        $this->production = $prod;

        return $this;
    }

    public function generateCsr(): array
    {
        $builder = new CertificateBuilder();

        $result = $builder->generateCSR(
            $this->commonName ?? config('zatca.seller.registration_name', ''),
            $this->serialNumber ?? '',
            $this->orgIdentifier ?? config('zatca.seller.vat_number', ''),
            $this->orgName ?? config('zatca.seller.registration_name', ''),
            $this->orgUnit ?? 'IT',
            $this->country ?? 'SA',
            $this->invoiceType ?? 1100,
            ! $this->production,
        );

        return $result;
    }

    public function requestCompliance(string $csr, string $otp): array
    {
        $environment = config('zatca.environment', 'sandbox');
        $manager = new ZatcaManager(['environment' => $environment]);

        $result = $manager->requestComplianceCertificate($csr, $otp);

        if (! empty($result['certificate']) && $this->deviceSerial) {
            $device = ZatcaDevice::firstOrCreate(
                ['serial_number' => $this->deviceSerial],
                [
                    'uuid'            => (string) \Illuminate\Support\Str::uuid(),
                    'name'            => $this->solutionName ?? 'Device',
                    'solution_name'   => $this->solutionName,
                    'model'           => $this->model,
                    'org_identifier'  => $this->orgIdentifier,
                    'environment'     => $environment,
                ]
            );

            ZatcaCertificate::create([
                'device_id'       => $device->id,
                'type'            => 'compliance',
                'certificate_pem' => $result['certificate'],
                'private_key_pem' => $result['private_key'] ?? '',
                'secret'          => $result['secret'] ?? '',
                'request_id'      => $result['request_id'] ?? null,
                'status'          => 'active',
            ]);
        }

        return $result;
    }

    public function requestProduction(array $complianceResult): array
    {
        $environment = config('zatca.environment', 'sandbox');
        $manager = new ZatcaManager([
            'environment' => $environment,
            'certificate' => $complianceResult['certificate'] ?? '',
            'private_key' => $complianceResult['private_key'] ?? '',
            'secret'      => $complianceResult['secret'] ?? '',
        ]);

        $result = $manager->requestProductionCertificate(
            $complianceResult['certificate'] ?? '',
            $complianceResult['secret'] ?? '',
            $complianceResult['request_id'] ?? '',
        );

        return $result;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        $cert = $this->getActiveCertificate();

        return $cert ? $cert->isExpiringSoon($days) : false;
    }

    public function daysUntilExpiry(): ?int
    {
        $cert = $this->getActiveCertificate();

        return $cert?->daysUntilExpiry();
    }

    public function info(): ?ZatcaCertificate
    {
        return $this->getActiveCertificate();
    }

    public function checkExpiringCertificates(): void
    {
        $days = config('zatca.notifications.certificate_expiry_days', 30);

        $expiring = ZatcaCertificate::where('status', 'active')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays($days))
            ->where('valid_until', '>', now())
            ->get();

        foreach ($expiring as $cert) {
            CertificateExpiring::dispatch($cert, $cert->daysUntilExpiry());
        }
    }

    private function getActiveCertificate(): ?ZatcaCertificate
    {
        $defaultDevice = config('zatca.default_device');

        $query = ZatcaCertificate::where('status', 'active')
            ->where('type', 'production');

        if ($defaultDevice) {
            $query->whereHas('device', fn ($q) => $q->where('uuid', $defaultDevice));
        }

        return $query->latest()->first();
    }
}

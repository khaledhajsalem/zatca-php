<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use Illuminate\Support\Str;
use KhaledHajSalem\ZatcaLaravel\Events\InvoiceCleared;
use KhaledHajSalem\ZatcaLaravel\Events\InvoiceCreated;
use KhaledHajSalem\ZatcaLaravel\Events\InvoiceRejected;
use KhaledHajSalem\ZatcaLaravel\Events\InvoiceReported;
use KhaledHajSalem\ZatcaLaravel\Events\InvoiceSubmitted;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaDevice;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaInvoice;
use KhaledHajSalem\ZatcaLaravel\Support\InvoiceResult;
use KhaledHajSalem\ZatcaLaravel\Support\InvoiceValidator;
use KhaledHajSalem\ZatcaPHP\Data\BuyerData;
use KhaledHajSalem\ZatcaPHP\Data\InvoiceData;
use KhaledHajSalem\ZatcaPHP\Data\InvoiceLineData;
use KhaledHajSalem\ZatcaPHP\Data\SellerData;
use KhaledHajSalem\ZatcaPHP\ZatcaManager;

class InvoiceBuilder
{
    private InvoiceData $invoiceData;
    private SellerData $sellerData;
    private ?BuyerData $buyerData = null;
    private array $lines = [];
    private ?string $deviceUuid = null;
    private bool $isStandard = false;
    private bool $isSimplified = false;

    public function __construct(
        private readonly ChainService $chainService,
        private readonly ApiLogService $apiLogService,
    ) {
        $this->invoiceData = new InvoiceData();
        $this->sellerData = $this->buildDefaultSeller();
    }

    public function standard(): self
    {
        $this->isStandard = true;
        $this->isSimplified = false;
        $this->invoiceData->standard();

        return $this;
    }

    public function simplified(): self
    {
        $this->isSimplified = true;
        $this->isStandard = false;
        $this->invoiceData->simplified();

        return $this;
    }

    public function taxInvoice(): self
    {
        $this->invoiceData->taxInvoice();

        return $this;
    }

    public function creditNote(): self
    {
        $this->invoiceData->creditNote();

        return $this;
    }

    public function debitNote(): self
    {
        $this->invoiceData->debitNote();

        return $this;
    }

    public function prepaymentInvoice(): self
    {
        $this->invoiceData->prepaymentInvoice();

        return $this;
    }

    public function number(string $invoiceNumber): self
    {
        $this->invoiceData->setInvoiceNumber($invoiceNumber);

        return $this;
    }

    public function date(string $issueDate, string $issueTime = '00:00:00'): self
    {
        $this->invoiceData->setIssueDate($issueDate);
        $this->invoiceData->setIssueTime($issueTime);

        return $this;
    }

    public function dueDate(string $dueDate): self
    {
        $this->invoiceData->setDueDate($dueDate);

        return $this;
    }

    public function currency(string $code): self
    {
        $this->invoiceData->setCurrencyCode($code);

        return $this;
    }

    public function billingReference(string $invoiceId, ?string $uuid = null): self
    {
        $this->invoiceData->addBillingReference($invoiceId, $uuid);

        return $this;
    }

    public function seller(callable $callback): self
    {
        $this->sellerData = new SellerData();
        $callback(new SellerConfigurator($this->sellerData));

        return $this;
    }

    public function buyer(callable $callback): self
    {
        $this->buyerData = new BuyerData();
        $callback(new BuyerConfigurator($this->buyerData));

        return $this;
    }

    public function line(string $name, float $qty, float $price, float $tax = 15.0, ?string $unit = null): self
    {
        $line = new InvoiceLineData();
        $line->setIndex(count($this->lines) + 1)
            ->setItemName($name)
            ->setQuantity($qty)
            ->setUnitPrice($price)
            ->setTaxPercent($tax)
            ->calculateTotals();

        if ($unit) {
            $line->setUnitCode($unit);
        }

        $this->lines[] = $line;

        return $this;
    }

    public function device(string $uuid): self
    {
        $this->deviceUuid = $uuid;

        return $this;
    }

    public function draft(): InvoiceResult
    {
        $this->prepare();

        $manager = $this->buildManager();
        $result = $manager->processInvoice(
            $this->invoiceData,
            $this->sellerData,
            $this->buyerData,
            $this->lines,
        );

        $invoice = $this->recordInvoice('draft', $result);
        InvoiceCreated::dispatch($invoice);

        return new InvoiceResult($invoice, $result);
    }

    public function submit(): InvoiceResult
    {
        $this->prepare();

        $validator = new InvoiceValidator();
        $errors = $validator->validate($this->invoiceData, $this->sellerData, $this->buyerData, $this->lines);
        if (! empty($errors)) {
            throw new \InvalidArgumentException('Invoice validation failed: ' . implode(', ', $errors));
        }

        $device = $this->resolveDevice();
        $manager = $this->buildManager($device);

        $result = $manager->processInvoice(
            $this->invoiceData,
            $this->sellerData,
            $this->buyerData,
            $this->lines,
        );

        $invoice = $this->recordInvoice('submitted', $result, $device);
        InvoiceCreated::dispatch($invoice);
        InvoiceSubmitted::dispatch($invoice, $result);

        $this->updateFromApiResult($invoice, $result);

        return new InvoiceResult($invoice, $result);
    }

    private function prepare(): void
    {
        $uuid = (string) Str::uuid();
        $this->invoiceData->setUUID($uuid);

        if (config('zatca.auto_chain')) {
            $device = $this->resolveDevice();
            if ($device) {
                $typeName = $this->invoiceData->getInvoiceTypeName();
                $chain = $this->chainService->getOrCreateChain($device->id, $typeName);
                $this->invoiceData->setInvoiceCounterValue((string) $chain->nextIcv());
                $this->invoiceData->setPreviousInvoiceHash($chain->last_pih);
            }
        }
    }

    private function resolveDevice(): ?ZatcaDevice
    {
        if ($this->deviceUuid) {
            return ZatcaDevice::where('uuid', $this->deviceUuid)->first();
        }

        $defaultUuid = config('zatca.default_device');
        if ($defaultUuid) {
            return ZatcaDevice::where('uuid', $defaultUuid)->first();
        }

        return ZatcaDevice::where('is_active', true)->first();
    }

    private function buildManager(?ZatcaDevice $device = null): ZatcaManager
    {
        $environment = config('zatca.environment', 'sandbox');

        $config = [
            'environment' => $environment,
        ];

        if ($device) {
            $cert = $device->activeCertificate;
            if ($cert) {
                $config['certificate'] = $cert->certificate_pem;
                $config['private_key'] = $cert->private_key_pem;
                $config['secret'] = $cert->secret;
            }
        }

        return new ZatcaManager($config);
    }

    private function buildDefaultSeller(): SellerData
    {
        $seller = new SellerData();
        $cfg = config('zatca.seller', []);

        if (! empty($cfg['registration_name'])) {
            $seller->setRegistrationName($cfg['registration_name']);
        }
        if (! empty($cfg['vat_number'])) {
            $seller->setTaxRegistrationNumber($cfg['vat_number']);
        }
        if (! empty($cfg['street'])) {
            $seller->setStreetName($cfg['street']);
        }
        if (! empty($cfg['building_number'])) {
            $seller->setBuildingNumber($cfg['building_number']);
        }
        if (! empty($cfg['city'])) {
            $seller->setCityName($cfg['city']);
        }
        if (! empty($cfg['district'])) {
            $seller->setDistrictName($cfg['district']);
        }
        if (! empty($cfg['postal_code'])) {
            $seller->setPostalCode($cfg['postal_code']);
        }
        if (! empty($cfg['country'])) {
            $seller->setCountryCode($cfg['country']);
        }
        if (! empty($cfg['party_id'])) {
            $seller->setPartyIdentification($cfg['party_id'], $cfg['party_id_scheme'] ?? 'CRN');
        }

        return $seller;
    }

    private function recordInvoice(string $status, array $result, ?ZatcaDevice $device = null): ZatcaInvoice
    {
        $totalExclusive = 0;
        $totalTax = 0;
        foreach ($this->lines as $line) {
            $totalExclusive += $line->getTaxExclusiveAmount();
            $totalTax += $line->getTaxAmount();
        }

        return ZatcaInvoice::create([
            'uuid'                 => $this->invoiceData->getUUID(),
            'device_id'            => $device?->id,
            'invoice_number'       => $this->invoiceData->getInvoiceNumber(),
            'invoice_type_code'    => $this->invoiceData->getInvoiceTypeCode(),
            'invoice_type_name'    => $this->invoiceData->getInvoiceTypeName(),
            'issue_date'           => $this->invoiceData->getIssueDate(),
            'issue_time'           => $this->invoiceData->getIssueTime(),
            'currency_code'        => $this->invoiceData->getCurrencyCode(),
            'seller_name'          => $this->sellerData->getRegistrationName(),
            'seller_vat'           => $this->sellerData->getTaxRegistrationNumber(),
            'buyer_name'           => $this->buyerData?->getRegistrationName(),
            'buyer_vat'            => $this->buyerData?->getTaxRegistrationNumber(),
            'tax_exclusive_amount' => $totalExclusive,
            'tax_amount'           => $totalTax,
            'tax_inclusive_amount'  => $totalExclusive + $totalTax,
            'payable_amount'       => $totalExclusive + $totalTax,
            'icv'                  => $this->invoiceData->getInvoiceCounterValue(),
            'pih'                  => $this->invoiceData->getPreviousInvoiceHash(),
            'invoice_hash'         => $result['invoice_hash'] ?? null,
            'qr_code'              => $result['qr_code'] ?? null,
            'signed_xml'           => config('zatca.store_xml') ? ($result['signed_xml'] ?? null) : null,
            'submission_type'      => $this->isStandard ? 'clearance' : 'reporting',
            'status'               => $status,
        ]);
    }

    private function updateFromApiResult(ZatcaInvoice $invoice, array $result): void
    {
        $validationResults = $result['validation_results'] ?? [];
        $zatcaStatus = $validationResults['status'] ?? null;
        $warnings = $validationResults['warningMessages'] ?? [];
        $errors = $validationResults['errorMessages'] ?? [];

        $newStatus = match (true) {
            ! empty($errors)                                        => 'rejected',
            ($result['clearance_status'] ?? null) === 'CLEARED'     => 'cleared',
            ($result['reporting_status'] ?? null) === 'REPORTED'    => 'reported',
            ! empty($warnings)                                       => 'warning',
            default                                                  => 'submitted',
        };

        $invoice->update([
            'status'          => $newStatus,
            'zatca_status'    => $zatcaStatus,
            'zatca_warnings'  => $warnings ?: null,
            'zatca_errors'    => $errors ?: null,
            'zatca_response'  => $result,
            'cleared_xml'     => $result['cleared_invoice'] ?? null,
            'submitted_at'    => now(),
        ]);

        if ($newStatus === 'cleared') {
            InvoiceCleared::dispatch($invoice, $result);
        } elseif ($newStatus === 'reported') {
            InvoiceReported::dispatch($invoice, $result);
        } elseif ($newStatus === 'rejected') {
            InvoiceRejected::dispatch($invoice, $errors);
        }

        if (config('zatca.auto_chain') && in_array($newStatus, ['cleared', 'reported'])) {
            $device = $invoice->device;
            if ($device && $invoice->invoice_hash) {
                $chain = $this->chainService->getOrCreateChain($device->id, $invoice->invoice_type_name);
                $chain->advance($invoice->invoice_hash, $invoice->id);
            }
        }
    }
}

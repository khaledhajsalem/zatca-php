<?php

namespace KhaledHajSalem\Zatca;

use KhaledHajSalem\Zatca\Data\InvoiceData;
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;
use KhaledHajSalem\Zatca\Services\ZatcaAPIService;
use KhaledHajSalem\Zatca\Support\Certificate;
use KhaledHajSalem\Zatca\Support\InvoiceSigner;

/**
 * Main manager class for ZATCA invoice processing.
 */
class ZatcaManager
{
    private array $config;
    private ZatcaAPIService $apiService;
    private Certificate $certificate;
    private ZatcaInvoice $zatcaInvoice;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'environment' => 'sandbox',
            'certificate_path' => '',
            'private_key_path' => '',
            'secret' => '',
            'timeout' => 30,
            'verify_ssl' => true,
            'allow_warnings' => true
        ], $config);

        $this->validateConfig();
        $this->initializeServices();
    }

    /**
     * Process a complete invoice workflow.
     *
     * @param InvoiceData $invoiceData
     * @param bool $isRetry
     * @return array
     * @throws ZatcaException
     */
    public function processInvoice(InvoiceData $invoiceData, bool $isRetry = false): array
    {
        try {
            // Generate UUID for the invoice first
            $uuid = $this->generateUUID();

            // Generate XML with the UUID
            $xml = $this->zatcaInvoice->generateXml($invoiceData, $uuid);
            
            // Sign the XML
            $signer = InvoiceSigner::signInvoice($xml, $this->certificate);
            $signedXml = $signer->getXML();
            $qrCode = $signer->getQRCode();
            $invoiceHash = $signer->getHash();

            // Determine if clearance or reporting is needed
            $isClearanceRequired = $this->isClearanceRequired($invoiceData);

            $response = [];
            if ($isClearanceRequired) {
                // Submit clearance API call
                $response = $this->apiService->clearInvoice(
                    $this->certificate->getRawCertificate(),
                    $this->config['secret'],
                    $signedXml,
                    $invoiceHash,
                    $uuid
                );
                
                // If cleared, use the cleared invoice
                if (isset($response['clearanceStatus']) && $response['clearanceStatus'] === 'CLEARED') {
                    $signedXml = base64_decode($response['clearedInvoice']);
                }
            } else {
                // Submit reporting API call
                $response = $this->apiService->reportInvoice(
                    $this->certificate->getRawCertificate(),
                    $this->config['secret'],
                    $signedXml,
                    $invoiceHash,
                    $uuid
                );
            }

            return [
                'qr_code' => $qrCode,
                'invoice_hash' => $invoiceHash,
                'xml' => $signedXml,
                'uuid' => $uuid,
                'response' => $response,
                'is_clearance_required' => $isClearanceRequired
            ];

        } catch (\Exception $e) {
            throw new ZatcaException('ZATCA processing error: ' . $e->getMessage(), [
                'invoice_number' => $invoiceData->getInvoiceNumber(),
                'error' => $e->getMessage()
            ], 0, $e);
        }
    }

    /**
     * Request compliance certificate.
     *
     * @param string $csr
     * @param string $otp
     * @return array
     * @throws ZatcaException
     */
    public function requestComplianceCertificate(string $csr, string $otp): array
    {
        try {
            $result = $this->apiService->requestComplianceCertificate($csr, $otp);
            return [
                'certificate' => $result->getCertificate(),
                'secret' => $result->getSecret(),
                'request_id' => $result->getRequestId()
            ];
        } catch (\Exception $e) {
            throw new ZatcaException('Failed to request compliance certificate: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Request production certificate.
     *
     * @param string $complianceRequestId
     * @return array
     * @throws ZatcaException
     */
    public function requestProductionCertificate(string $complianceRequestId): array
    {
        try {
            $result = $this->apiService->requestProductionCertificate(
                $this->certificate->getRawCertificate(),
                $this->config['secret'],
                $complianceRequestId
            );
            return [
                'certificate' => $result->getCertificate(),
                'secret' => $result->getSecret(),
                'request_id' => $result->getRequestId()
            ];
        } catch (\Exception $e) {
            throw new ZatcaException('Failed to request production certificate: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Validate invoice compliance.
     *
     * @param string $signedXml
     * @param string $invoiceHash
     * @param string $uuid
     * @return array
     * @throws ZatcaException
     */
    public function validateInvoiceCompliance(string $signedXml, string $invoiceHash, string $uuid): array
    {
        try {
            return $this->apiService->validateInvoiceCompliance(
                $this->certificate->getRawCertificate(),
                $this->config['secret'],
                $signedXml,
                $invoiceHash,
                $uuid
            );
        } catch (\Exception $e) {
            throw new ZatcaException('Failed to validate invoice compliance: ' . $e->getMessage(), [], 0, $e);
        }
    }



    /**
     * Get the API service instance.
     */
    public function getApiService(): ZatcaAPIService
    {
        return $this->apiService;
    }

    /**
     * Get the certificate instance.
     */
    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }

    /**
     * Validate configuration.
     */
    private function validateConfig(): void
    {
        $required = ['certificate_path', 'private_key_path', 'secret'];
        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                throw new ZatcaException("Missing required configuration: $field");
            }
        }

        if (!file_exists($this->config['certificate_path'])) {
            throw new ZatcaException("Certificate file not found: {$this->config['certificate_path']}");
        }

        if (!file_exists($this->config['private_key_path'])) {
            throw new ZatcaException("Private key file not found: {$this->config['private_key_path']}");
        }
    }

    /**
     * Initialize services.
     */
    private function initializeServices(): void
    {
        $this->apiService = new ZatcaAPIService($this->config['environment']);
        $this->apiService->setWarningHandling($this->config['allow_warnings']);

        $this->certificate = new Certificate(
            file_get_contents($this->config['certificate_path']),
            file_get_contents($this->config['private_key_path']),
            $this->config['secret']
        );

        $this->zatcaInvoice = new ZatcaInvoice();
    }

    /**
     * Determine if clearance is required based on invoice type.
     * 
     * Based on ZATCA requirements:
     * - Standard Tax Invoice (388 with name "0100000") - Requires clearance
     * - Simplified Tax Invoice (388 with name "0200000") - No clearance required
     * - Debit Note (383) - No clearance required
     * - Credit Note (381) - No clearance required
     * - Prepayment Invoice (386) - No clearance required
     */
    private function isClearanceRequired(InvoiceData $invoiceData): bool
    {
        $invoiceTypeCode = $invoiceData->getInvoiceTypeCode();
        
        // Only Standard Tax Invoice (388) with name "0100000" requires clearance
        // Simplified Tax Invoice (388) with name "0200000" does not require clearance
        return $invoiceTypeCode === '388' && $invoiceData->getInvoiceTypeName() === '0100000';
    }

    /**
     * Generate a UUID for the invoice.
     */
    private function generateUUID(): string
    {
        if (function_exists('random_bytes')) {
            $data = random_bytes(16);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            $data = uniqid('', true);
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
} 
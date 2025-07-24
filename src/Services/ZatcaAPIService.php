<?php

namespace KhaledHajSalem\Zatca\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use KhaledHajSalem\Zatca\Exceptions\ZatcaApiException;
use InvalidArgumentException;

class ZatcaAPIService
{
    private const ENVIRONMENTS = [
        'sandbox'    => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
        'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
    ];

    private const API_VERSION = 'V2';
    private const SUCCESS_STATUS_CODES = [200, 202];

    private Client $httpClient;
    private bool $allowWarnings = true;
    private string $environment;
    private string $logChannel = 'zatca';
    private bool $debug = false;

    public function getLogChannel(): string
    {
        return $this->logChannel;
    }

    /**
     * Create a new ZatcaService instance.
     *
     * @param string $environment API environment (sandbox|simulation|production)
     * @throws InvalidArgumentException For invalid environment.
     */
    public function __construct(string $environment = 'sandbox')
    {
        if (!isset(self::ENVIRONMENTS[$environment])) {
            $validEnvs = implode(', ', array_keys(self::ENVIRONMENTS));
            throw new InvalidArgumentException("Invalid environment. Valid options: $validEnvs");
        }

        $this->environment = $environment;
        $this->httpClient = new Client([
            'base_uri' => $this->getBaseUri(),
            'timeout'  => 30,
            'verify'   => true,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx status codes
        ]);
    }

    /**
     * Returns the base URI for the current environment.
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return self::ENVIRONMENTS[$this->environment];
    }

    /**
     * Enable/disable acceptance of warning responses.
     */
    public function setWarningHandling(bool $allow): void
    {
        $this->allowWarnings = $allow;
    }

    /**
     * Enable/disable debug mode for detailed request/response logging.
     */
    public function setDebugMode(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Request compliance certificate using CSR and OTP.
     *
     * @param string $csr CSR content.
     * @param string $otp One-Time Password.
     * @return ComplianceCertificateResult
     * @throws ZatcaApiException For API communication errors.
     */
    public function requestComplianceCertificate(string $csr, string $otp): ComplianceCertificateResult
    {
        try {
            $response = $this->sendRequest(
                'POST',
                '/compliance',
                ['OTP' => $otp],
                ['csr' => base64_encode($csr)]
            );

            return new ComplianceCertificateResult(
                $this->formatCertificate($response['binarySecurityToken'] ?? ''),
                $response['secret'] ?? '',
                $response['requestID'] ?? ''
            );
        } catch (ZatcaApiException $e) {
            throw $e;
        }
    }

    /**
     * Validate invoice compliance with ZATCA regulations.
     *
     * @param string $certificate The certificate for authentication.
     * @param string $secret API secret.
     * @param string $signedInvoice Signed invoice content.
     * @param string $invoiceHash Invoice hash.
     * @param string $uuid Unique invoice identifier.
     * @return array API response data.
     * @throws ZatcaApiException For API communication errors.
     */
    public function validateInvoiceCompliance(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ): array {
        try {
            $response = $this->sendRequest(
                'POST',
                '/compliance/invoices',
                ['Accept-Language' => 'en', 'Content-Type' => 'application/json'],
                [
                    'invoiceHash' => $invoiceHash,
                    'uuid'        => $uuid,
                    'invoice'     => base64_encode($signedInvoice),
                ],
                $this->createAuthHeaders($certificate, $secret)
            );

            return $response;
        } catch (ZatcaApiException $e) {
            if ($this->allowWarnings) {
                // Handle warnings if allowed
                return [];
            }
            throw $e;
        }
    }

    /**
     * Request production certificate using compliance credentials.
     *
     * @param string $certificate The certificate for authentication.
     * @param string $secret API secret.
     * @param string $complianceRequestId Compliance request ID.
     * @return ProductionCertificateResult
     * @throws ZatcaApiException For API communication errors.
     */
    public function requestProductionCertificate(
        string $certificate,
        string $secret,
        string $complianceRequestId
    ): ProductionCertificateResult {
        try {
            $response = $this->sendRequest(
                'POST',
                '/production/csids',
                ['Content-Type' => 'application/json'],
                ['compliance_request_id' => $complianceRequestId],
                $this->createAuthHeaders($certificate, $secret)
            );

            return new ProductionCertificateResult(
                $this->formatCertificate($response['binarySecurityToken'] ?? ''),
                $response['secret'] ?? '',
                $response['requestID'] ?? ''
            );
        } catch (ZatcaApiException $e) {
            throw $e;
        }
    }

    /**
     * Clear invoice with ZATCA.
     *
     * @param string $certificate The certificate for authentication.
     * @param string $secret API secret.
     * @param string $signedInvoice Signed invoice content.
     * @param string $invoiceHash Invoice hash.
     * @param string $uuid Unique invoice identifier.
     * @return array API response data.
     * @throws ZatcaApiException For API communication errors.
     */
    public function clearInvoice(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ): array {
        try {
            $response = $this->sendRequest(
                'POST',
                '/invoices/clearance/single',
                ['Clearance-Status' => '1', 'Accept-Language' => 'en'],
                [
                    'invoiceHash' => $invoiceHash,
                    'uuid'        => $uuid,
                    'invoice'     => base64_encode($signedInvoice),
                ],
                $this->createAuthHeaders($certificate, $secret)
            );

            return $response;
        } catch (ZatcaApiException $e) {
            throw $e;
        }
    }

    /**
     * Report an invoice to ZATCA.
     *
     * @param string $certificate The certificate for authentication.
     * @param string $secret API secret.
     * @param string $signedInvoice Signed invoice content.
     * @param string $invoiceHash Invoice hash.
     * @param string $uuid Unique invoice identifier.
     * @return array API response data.
     * @throws ZatcaApiException For API communication errors.
     */
    public function reportInvoice(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ): array {
        try {
            $response = $this->sendRequest(
                'POST',
                '/invoices/reporting/single',
                ['Accept-Language' => 'en'],
                [
                    'invoiceHash' => $invoiceHash,
                    'uuid'        => $uuid,
                    'invoice'     => base64_encode($signedInvoice),
                ],
                $this->createAuthHeaders($certificate, $secret)
            );

            return $response;
        } catch (ZatcaApiException $e) {
            throw $e;
        }
    }



    /**
     * Generate authentication headers for secured endpoints.
     *
     * @param string $certificate
     * @param string $secret
     * @return array
     */
    private function createAuthHeaders(string $certificate, string $secret): array
    {
        // Extract the base64 certificate content from PEM format
        $cleanCert = $this->extractCertificateContent($certificate);
        $base64Cert = base64_encode($cleanCert);
        $credentials = base64_encode($base64Cert . ':' . $secret);
        return ['Authorization' => 'Basic ' . $credentials];
    }

    /**
     * Extract certificate content from PEM format.
     *
     * @param string $pemCertificate PEM formatted certificate
     * @return string Certificate content without headers
     */
    private function extractCertificateContent(string $pemCertificate): string
    {
        // Remove PEM headers and footers
        $content = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $pemCertificate);
        $content = preg_replace('/-----END CERTIFICATE-----/', '', $content);
        
        // Remove any whitespace and newlines
        $content = preg_replace('/\s+/', '', $content);

        return trim($content);
    }

    /**
     * Core request handling with Guzzle.
     *
     * @param string $method HTTP method.
     * @param string $endpoint API endpoint.
     * @param array $headers Additional headers.
     * @param array $payload Request payload.
     * @param array $authHeaders Optional auth headers.
     * @return array Decoded response data.
     * @throws ZatcaApiException On HTTP or API errors.
     */
    private function sendRequest(
        string $method,
        string $endpoint,
        array $headers = [],
        array $payload = [],
        array $authHeaders = []
    ): array {
        $url = $this->getBaseUri().$endpoint;

        try {
            $mergedHeaders = array_merge(
                [
                    'Accept-Version' => self::API_VERSION,
                    'Accept'         => 'application/json',
                ],
                $headers,
                $authHeaders
            );

            $options = [
                'headers' => $mergedHeaders,
                'json'    => $payload,
            ];

            // Debug: Log the complete request details
            if ($this->debug) {
                $this->debugRequest($method, $url, $mergedHeaders, $payload);
            }

            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            
            // Debug: Log the response details
            if ($this->debug) {
                $this->debugResponse($response, $statusCode);
            }

            if (!$this->isSuccessfulResponse($statusCode)) {
                $responseData = $this->parseResponse($response);
                $errorMessage = $statusCode === 400 
                    ? "ZATCA API validation error" 
                    : "ZATCA API returned error response";
                    
                throw new ZatcaApiException($errorMessage, [
                    'endpoint' => $url,
                    'status_code' => $statusCode,
                    'response' => $responseData,
                ]);
            }

            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            throw new ZatcaApiException('HTTP request failed: ' . $e->getMessage(), [
                'endpoint' => $url,
            ], $e->getCode(), new \Exception($e->getMessage(), $e->getCode(), $e));
        }
    }

    /**
     * Validate HTTP status code against success criteria.
     */
    private function isSuccessfulResponse(int $statusCode): bool
    {
        // Allow 400 for validation errors from ZATCA
        if ($statusCode === 400) {
            return true;
        }
        
        return in_array($statusCode, self::SUCCESS_STATUS_CODES, true) &&
            ($this->allowWarnings || $statusCode === 200);
    }

    /**
     * Parse API response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     * @throws ZatcaApiException If response JSON is invalid.
     */
    private function parseResponse($response): array
    {
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            throw new ZatcaApiException('Failed to parse API response: ' . $error);
        }

        return $data;
    }

    /**
     * Format certificate string with PEM boundaries.
     *
     * @param string $base64Certificate
     * @return string
     */
    private function formatCertificate(string $base64Certificate): string
    {
        return base64_decode($base64Certificate);
    }

    /**
     * Debug method to log complete request details.
     *
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $headers Request headers
     * @param array $payload Request payload
     */
    private function debugRequest(string $method, string $url, array $headers, array $payload): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "🔍 ZATCA API REQUEST DEBUG\n";
        echo str_repeat("=", 80) . "\n";
        echo "📤 METHOD: {$method}\n";
        echo "🌐 URL: {$url}\n";
        echo "📋 HEADERS:\n";
        
        foreach ($headers as $name => $value) {
            // Mask sensitive headers
            if (strtolower($name) === 'authorization') {
                $maskedValue = substr($value, 0, 20) . '...' . substr($value, -10);
                echo "   {$name}: {$maskedValue}\n";
            } else {
                echo "   {$name}: {$value}\n";
            }
        }
        
        echo "📦 PAYLOAD:\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        echo str_repeat("=", 80) . "\n";
    }

    /**
     * Debug method to log response details.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int $statusCode
     */
    private function debugResponse($response, int $statusCode): void
    {
        echo "📥 RESPONSE:\n";
        echo "📊 STATUS CODE: {$statusCode}\n";
        echo "📋 RESPONSE HEADERS:\n";
        
        foreach ($response->getHeaders() as $name => $values) {
            echo "   {$name}: " . implode(', ', $values) . "\n";
        }
        
        $body = $response->getBody()->getContents();
        echo "📦 RESPONSE BODY:\n";
        
        // Try to format as JSON if possible
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            var_dump($body);
        }
        echo $body . "\n";
        echo str_repeat("=", 80) . "\n\n";
        
        // Reset the body stream so it can be read again
        $response->getBody()->rewind();
    }
}

/**
 * Data transfer object for compliance certificate results.
 */
class ComplianceCertificateResult
{
    private string $certificate;
    private string $secret;
    private string $requestId;

    public function __construct(string $certificate, string $secret, string $requestId)
    {
        $this->certificate = $certificate;
        $this->secret = $secret;
        $this->requestId = $requestId;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}

/**
 * Data transfer object for production certificate results.
 */
class ProductionCertificateResult
{
    private string $certificate;
    private string $secret;
    private string $requestId;

    public function __construct(string $certificate, string $secret, string $requestId)
    {
        $this->certificate = $certificate;
        $this->secret = $secret;
        $this->requestId = $requestId;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}

<?php

namespace KhaledHajSalem\Zatca\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
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

    private ClientInterface $httpClient;
    private bool $allowWarnings = true;
    private string $environment;
    private string $logChannel = 'zatca';
    private bool $debug = false;
    private string $language = 'en';
    private ?int $lastStatusCode = null;

    public function getLogChannel(): string
    {
        return $this->logChannel;
    }

    /**
     * Create a new ZatcaService instance.
     *
     * @param string $environment API environment (sandbox|simulation|production)
     * @param ClientInterface|null $httpClient Your own Guzzle client (7 or 8),
     *        for example to add a proxy, middleware or a mock handler in
     *        tests. It must not throw on 4xx/5xx ("http_errors" => false).
     * @param array $options Used when no client is given: "timeout" (seconds,
     *        default 30), "connect_timeout" (default 10), "verify" (TLS
     *        verification, default true). Always: "language" ("en" or "ar")
     *        for the messages ZATCA returns.
     * @throws InvalidArgumentException For invalid environment.
     */
    public function __construct(string $environment = 'sandbox', ?ClientInterface $httpClient = null, array $options = [])
    {
        if (!isset(self::ENVIRONMENTS[$environment])) {
            $validEnvs = implode(', ', array_keys(self::ENVIRONMENTS));
            throw new InvalidArgumentException("Invalid environment. Valid options: $validEnvs");
        }

        $this->environment = $environment;
        $this->language = in_array($options['language'] ?? 'en', ['en', 'ar'], true) ? ($options['language'] ?? 'en') : 'en';
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->getBaseUri(),
            'timeout'  => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'verify'   => $options['verify'] ?? true,
            'http_errors' => false, // Don't throw exceptions for 4xx/5xx status codes
        ]);
    }

    /**
     * HTTP status code of the most recent response, or null when the last
     * call received no response (timeout, DNS, TLS) or none was made yet.
     * Lets callers of the array-returning methods tell 200 from 202.
     */
    public function getLastStatusCode(): ?int
    {
        return $this->lastStatusCode;
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
        // Warnings are returned as successful (202) responses by sendRequest(),
        // so any exception here is a real failure and must not be swallowed.
        return $this->sendRequest(
            'POST',
            '/compliance/invoices',
            ['Accept-Language' => $this->language, 'Content-Type' => 'application/json'],
            [
                'invoiceHash' => $invoiceHash,
                'uuid'        => $uuid,
                'invoice'     => base64_encode($signedInvoice),
            ],
            $this->createAuthHeaders($certificate, $secret)
        );
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
                ['Clearance-Status' => '1', 'Accept-Language' => $this->language],
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
                ['Clearance-Status' => '0', 'Accept-Language' => $this->language],
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
     * Report a simplified document and get every HTTP outcome back as a
     * SubmissionResult, including 409 (already reported), 400 (rejected),
     * 401 and 5xx, instead of an exception.
     *
     * @throws ZatcaApiException Only when no response was received at all
     *         (timeout, DNS, TLS). The outcome is then unknown and the same
     *         document may be sent again.
     */
    public function report(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ): SubmissionResult {
        [$status, $body] = $this->dispatch(
            'POST',
            '/invoices/reporting/single',
            ['Clearance-Status' => '0', 'Accept-Language' => $this->language],
            $this->invoicePayload($signedInvoice, $invoiceHash, $uuid),
            $this->createAuthHeaders($certificate, $secret)
        );

        return new SubmissionResult('reporting', $status, $body);
    }

    /**
     * Clear a standard document and get every HTTP outcome back as a
     * SubmissionResult, including 208 (hash already submitted; the cleared
     * invoice is returned again) and 303 (clearance deactivated).
     *
     * @throws ZatcaApiException Only when no response was received at all.
     */
    public function clear(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ): SubmissionResult {
        [$status, $body] = $this->dispatch(
            'POST',
            '/invoices/clearance/single',
            ['Clearance-Status' => '1', 'Accept-Language' => $this->language],
            $this->invoicePayload($signedInvoice, $invoiceHash, $uuid),
            $this->createAuthHeaders($certificate, $secret)
        );

        return new SubmissionResult('clearance', $status, $body);
    }

    /**
     * Run a compliance check with the compliance CSID and get every HTTP
     * outcome back as a SubmissionResult.
     *
     * @throws ZatcaApiException Only when no response was received at all.
     */
    public function checkCompliance(
        string $certificate,
        string $secret,
        string $signedInvoice,
        string $invoiceHash,
        string $uuid
    ): SubmissionResult {
        [$status, $body] = $this->dispatch(
            'POST',
            '/compliance/invoices',
            ['Accept-Language' => $this->language, 'Content-Type' => 'application/json'],
            $this->invoicePayload($signedInvoice, $invoiceHash, $uuid),
            $this->createAuthHeaders($certificate, $secret)
        );

        return new SubmissionResult('compliance', $status, $body);
    }

    /**
     * Renew a production certificate (PATCH /production/csids).
     *
     * Authenticated with the CURRENT production certificate and secret. The
     * OTP comes from the Fatoora portal of the environment; the CSR is a new
     * one for the same EGS unit.
     *
     * @param string $certificate Current production certificate.
     * @param string $secret Current production secret.
     * @param string $csr New CSR content.
     * @param string $otp One-Time Password.
     * @throws ZatcaApiException For API errors. ZATCA answers 428 with
     *         dispositionMessage NOT_COMPLIANT and a compliance certificate
     *         when the compliance checks must be passed again first; the
     *         exception context then holds that response.
     */
    public function renewProductionCertificate(
        string $certificate,
        string $secret,
        string $csr,
        string $otp
    ): ProductionCertificateResult {
        [$status, $body] = $this->dispatch(
            'PATCH',
            '/production/csids',
            ['OTP' => $otp, 'Accept-Language' => $this->language, 'Content-Type' => 'application/json'],
            ['csr' => base64_encode($csr)],
            $this->createAuthHeaders($certificate, $secret)
        );

        if ($status !== 200) {
            $message = $status === 428
                ? 'ZATCA requires the compliance checks to be passed again before renewal (428 NOT_COMPLIANT)'
                : "ZATCA API returned HTTP $status";

            throw new ZatcaApiException($message, [
                'endpoint' => $this->getBaseUri() . '/production/csids',
                'status_code' => $status,
                'response' => $body,
            ]);
        }

        return new ProductionCertificateResult(
            $this->formatCertificate($body['binarySecurityToken'] ?? ''),
            $body['secret'] ?? '',
            (string)($body['requestID'] ?? '')
        );
    }

    private function invoicePayload(string $signedInvoice, string $invoiceHash, string $uuid): array
    {
        return [
            'invoiceHash' => $invoiceHash,
            'uuid'        => $uuid,
            'invoice'     => base64_encode($signedInvoice),
        ];
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
     * Core request handling with Guzzle, with the legacy success rules:
     * 200, 202 (when warnings are allowed) and 400 return the decoded body;
     * every other status throws.
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
        [$statusCode, $responseData, $reasonPhrase] = $this->dispatch($method, $endpoint, $headers, $payload, $authHeaders);

        if (!$this->isSuccessfulResponse($statusCode)) {
            $errorMessage = $statusCode === 400
                ? "ZATCA API validation error"
                : "ZATCA API returned HTTP $statusCode " . $reasonPhrase;

            throw new ZatcaApiException($errorMessage, [
                'endpoint' => $this->getBaseUri() . $endpoint,
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);
        }

        return $responseData;
    }

    /**
     * Send a request and return what came back, without judging it.
     *
     * @return array{0: int, 1: array, 2: string} Status code, decoded body, reason phrase.
     * @throws ZatcaApiException When no response was received (the context
     *         has no "status_code") or the body is not valid JSON.
     */
    private function dispatch(
        string $method,
        string $endpoint,
        array $headers = [],
        array $payload = [],
        array $authHeaders = []
    ): array {
        $url = $this->getBaseUri() . $endpoint;
        $this->lastStatusCode = null;

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
                // A client supplied by the caller may be configured to
                // throw on 4xx/5xx; every status is handled here instead.
                'http_errors' => false,
                // 303 is an answer (clearance deactivated), not a redirect.
                'allow_redirects' => false,
            ];

            // Debug: Log the complete request details
            if ($this->debug) {
                $this->debugRequest($method, $url, $mergedHeaders, $payload);
            }

            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $this->lastStatusCode = $statusCode;

            // Debug: Log the response details
            if ($this->debug) {
                $this->debugResponse($response, $statusCode);
            }

            return [$statusCode, $this->parseResponse($response), $response->getReasonPhrase()];
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

        // Some error responses (e.g. 401 Unauthorized) have an empty body.
        if (trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Error responses are not always JSON (406 is plain text, gateways
            // answer in HTML). Keep the text so the status code still reaches
            // the caller; only a 2xx with an unreadable body is a failure.
            if ($response->getStatusCode() >= 300) {
                return ['message' => trim(substr($content, 0, 2000))];
            }

            $error = json_last_error_msg();
            throw new ZatcaApiException('Failed to parse API response: ' . $error, [
                'status_code' => $response->getStatusCode(),
                'body' => $content,
            ]);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Format certificate string with PEM boundaries.
     *
     * @param string $base64Certificate
     * @return string
     */
    private function formatCertificate(string $base64Certificate): string
    {
        $decoded = base64_decode($base64Certificate);

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($decoded, 64, "\n")
            . "-----END CERTIFICATE-----";
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

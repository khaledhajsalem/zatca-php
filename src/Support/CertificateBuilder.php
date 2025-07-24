<?php

namespace KhaledHajSalem\Zatca\Support;

use KhaledHajSalem\Zatca\Exceptions\CertificateBuilderException;
use KhaledHajSalem\Zatca\Services\Storage;
use KhaledHajSalem\Zatca\Exceptions\ZatcaStorageException;

class CertificateBuilder {
    private const OID_PROD = 'ZATCA-Code-Signing';
    private const OID_TEST = 'PREZATCA-Code-Signing';
    private const CONFIG_TEMPLATE = <<<EOL
[req]
prompt = no
utf8 = no
distinguished_name = req_dn

[req_dn]

[v3_req]
1.3.6.1.4.1.311.20.2 = ASN1:PRINTABLESTRING:%s
subjectAltName = dirName:dir_sect

[dir_sect]
EOL;

    /** @var string */
    private string $organizationIdentifier;
    /** @var string */
    private string $serialNumber;
    /** @var string */
    private string $commonName = '';
    /** @var string */
    private string $country = 'SA';
    /** @var string */
    private string $organizationName = '';
    /** @var string */
    private string $organizationalUnitName = '';
    /** @var string */
    private string $address = '';
    /** @var int */
    private int $invoiceType = 1100;
    /** @var bool */
    private bool $production = false;
    /** @var string */
    private string $businessCategory = '';

    /**
     * In PHP 8.0+, openssl_pkey_new returns an OpenSSLAsymmetricKey object.
     * In earlier versions, it returns a resource.
     *
     * @var resource|\OpenSSLAsymmetricKey|null
     */
    private $privateKey = null;

    /**
     * The CSR resource/object.
     *
     * @var resource|\OpenSSLCertificateSigningRequest|null
     */
    private $csr = null;

    /**
     * Set organization identifier (15 digits, starts and ends with 3).
     */
    public function setOrganizationIdentifier(string $identifier): self {
        if (!preg_match('/^3\d{13}3$/', $identifier)) {
            throw new CertificateBuilderException('Org Identifier must be 15 digits starting and ending with 3.');
        }
        $this->organizationIdentifier = $identifier;
        return $this;
    }

    /**
     * Set serial number using solution name, model, and serial.
     */
    public function setSerialNumber(string $solutionName, string $model, string $serialNumber): self {
        $this->serialNumber = sprintf(
            '1-%s|2-%s|3-%s',
            $this->sanitize($solutionName),
            $this->sanitize($model),
            $this->sanitize($serialNumber)
        );
        return $this;
    }

    /**
     * Set common name.
     */
    public function setCommonName(string $name): self {
        $this->commonName = $this->sanitize($name);
        return $this;
    }

    /**
     * Set 2-character country code.
     */
    public function setCountryName(string $country): self {
        if (strlen($country) !== 2) {
            throw new CertificateBuilderException('Country code must be 2 characters.');
        }
        $this->country = strtoupper($country);
        return $this;
    }

    /**
     * Set organization name.
     */
    public function setOrganizationName(string $name): self {
        $this->organizationName = $this->sanitize($name);
        return $this;
    }

    /**
     * Set organizational unit.
     */
    public function setOrganizationalUnitName(string $name): self {
        $this->organizationalUnitName = $this->sanitize($name);
        return $this;
    }

    /**
     * Set address.
     */
    public function setAddress(string $address): self {
        $this->address = $this->sanitize($address);
        return $this;
    }

    /**
     * Set invoice type (0- to 4-digit number).
     * Four digits, each digit acting as a bool. The order is as follows: Standard Invoice, Simplified, future use, future use
     */
    public function setInvoiceType(int $type): self {
        if ($type < 0 || $type > 9999) {
            throw new CertificateBuilderException('Invoice type must be a 4-digit number.');
        }
        $this->invoiceType = $type;
        return $this;
    }

    /**
     * Set production flag (true for production).
     */
    public function setProduction(bool $production): self {
        $this->production = $production;
        return $this;
    }

    /**
     * Set business category.
     */
    public function setBusinessCategory(string $category): self {
        $this->businessCategory = $this->sanitize($category);
        return $this;
    }

    /**
     * Generate CSR and private key.
     */
    public function generate(): void {
        $this->validateParameters();
        $config = $this->createOpenSslConfig();
        try {
            $this->generateKeys($config);
        } finally {
            if (isset($config['config']) && file_exists($config['config'])) {
                unlink($config['config']);
            }
        }
    }

    /**
     * Generate and save CSR and key to files.
     *
     * @param string $csrPath Path to save the CSR (default: certificate.csr)
     * @param string $privateKeyPath Path to save the private key (default: private.pem)
     * @throws CertificateBuilderException
     */
    public function generateAndSave(string $csrPath = 'certificate.csr', string $privateKeyPath = 'private.pem'): void {
        $this->generate();

        $csrContent = $this->getCsr();

        try {
            (new Storage())->put($csrPath, $csrContent);
        } catch (ZatcaStorageException $e) {
            throw new CertificateBuilderException("Failed to save CSR.", $e->getContext());
        }

        $this->savePrivateKey($privateKeyPath);
    }

    /**
     * Get CSR as a string.
     */
    public function getCsr(): string {
        if (!$this->csr) {
            throw new CertificateBuilderException('CSR not generated. Call generate() first.');
        }
        /** @var resource|\OpenSSLCertificateSigningRequest $csr */
        $csr = $this->csr;
        if (!openssl_csr_export($csr, $csrString)) {
            throw new CertificateBuilderException('CSR export failed: ' . $this->getOpenSslErrors());
        }
        return $csrString;
    }

    /**
     * Save private key to a file.
     */
    public function savePrivateKey(string $path): void {
        /** @var resource|\OpenSSLAsymmetricKey $privateKey */
        $privateKey = $this->privateKey;
        if (!openssl_pkey_export_to_file($privateKey, $path)) {
            throw new CertificateBuilderException('Private key export failed: ' . $this->getOpenSslErrors());
        }
    }

    /**
     * Validate required parameters.
     */
    private function validateParameters(): void {
        $required = [
            'organizationIdentifier',
            'serialNumber',
            'commonName',
            'organizationName',
            'organizationalUnitName',
            'address',
            'businessCategory'
        ];
        foreach ($required as $param) {
            if (empty($this->$param)) {
                throw new CertificateBuilderException("Missing required parameter: $param");
            }
        }
    }

    /**
     * Create OpenSSL config array.
     *
     * @return array
     */
    private function createOpenSslConfig(): array {
        return [
            "digest_alg"       => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "curve_name"       => "secp256k1",
            "req_extensions"   => "v3_req",
            "config"           => $this->createConfigFile()
        ];
    }

    /**
     * Create a temporary OpenSSL config file.
     *
     * @return string The path to the config file.
     * @throws CertificateBuilderException
     */
    private function createConfigFile(): string {
        $dirSection = [
            'SN'                => $this->serialNumber,
            'UID'               => $this->organizationIdentifier,
            'title'             => (string)$this->invoiceType,
            'registeredAddress' => $this->address,
            'businessCategory'  => $this->businessCategory,
        ];

        $configContent = sprintf(
                self::CONFIG_TEMPLATE,
                $this->production ? self::OID_PROD : self::OID_TEST
            ) . "\n";

        foreach ($dirSection as $key => $value) {
            $configContent .= "$key = $value\n";
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'zatca_');
        if ($tempFile === false) {
            throw new CertificateBuilderException('Failed to create temporary config file.');
        }

        try {
            (new Storage)->put($tempFile, $configContent);
        } catch (ZatcaStorageException $e) {
            throw new CertificateBuilderException('Failed to write temporary config file.', $e->getContext());
        }

        return $tempFile;
    }

    /**
     * Generate keys and CSR.
     *
     * @param array $config OpenSSL configuration array.
     */
    private function generateKeys(array $config): void {
        $this->privateKey = openssl_pkey_new($config);
        if ($this->privateKey === false) {
            throw new CertificateBuilderException('Key generation failed: ' . $this->getOpenSslErrors());
        }

        $dn = [
            "CN"                     => $this->commonName,
            "organizationName"       => $this->organizationName,
            "organizationalUnitName" => $this->organizationalUnitName,
            "C"                      => $this->country
        ];

        /** @var resource|\OpenSSLAsymmetricKey $privateKey */
        $privateKey = $this->privateKey;
        $this->csr = openssl_csr_new($dn, $privateKey, $config);
        if ($this->csr === false) {
            throw new CertificateBuilderException('CSR generation failed: ' . $this->getOpenSslErrors());
        }
    }

    /**
     * Sanitize input.
     */
    private function sanitize(string $input): string {
        $trimmed = trim($input);
        $sanitized = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $trimmed);
        if ($sanitized === null) {
            throw new CertificateBuilderException("Sanitization failed for: $input");
        }
        return $sanitized;
    }

    /**
     * Retrieve all OpenSSL error messages.
     */
    private function getOpenSslErrors(): string {
        $errors = [];
        while ($msg = openssl_error_string()) {
            $errors[] = $msg;
        }
        return implode("; ", $errors);
    }

    /**
     * Free private key resource if necessary.
     */
    public function __destruct() {
        if ($this->privateKey && is_resource($this->privateKey)) {
            // todo DEPRECATED https://www.php.net/manual/en/function.openssl-pkey-free.php
            openssl_pkey_free($this->privateKey);
        }
    }
}
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KhaledHajSalem\Zatca\Support\CertificateBuilder;
use KhaledHajSalem\Zatca\Exceptions\CertificateBuilderException;

// Example: Certificate Generation

try {
    // 1. Create certificate builder
    $builder = new CertificateBuilder();
    
    // 2. Set required parameters
    $builder->setOrganizationIdentifier('300000000000003') // 15 digits starting and ending with 3
        ->setSerialNumber('MySolution', 'Model1', 'SN001') // Solution name, model, serial number
        ->setCommonName('Your Company Name')
        ->setCountryName('SA') // 2-character country code
        ->setOrganizationName('Your Company Name')
        ->setOrganizationalUnitName('IT Department')
        ->setAddress('123 Main Street, Riyadh, Saudi Arabia')
        ->setInvoiceType(1100) // Invoice type code  # Four digits, each digit acting as a bool. The order is as follows: Standard Invoice, Simplified, future use, future use 
        ->setProduction(false) // false for sandbox, true for production
        ->setBusinessCategory('Legal Entity'); // Business category: Fashion, Legal Entity, Technology, Other

    // 3. Generate CSR and private key
    $builder->generateAndSave('storage/certificate.csr', 'storage/private.pem');

    echo "Certificate generation completed successfully!\n";
    echo "CSR saved to: certificate.csr\n";
    echo "Private key saved to: private.pem\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Submit the CSR to ZATCA for compliance testing\n";
    echo "2. Use the OTP received from ZATCA to request compliance certificate\n";
    echo "3. After compliance testing, request production certificate\n";

} catch (CertificateBuilderException $e) {
    echo "Certificate Builder Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
} 
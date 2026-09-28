<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KhaledHajSalem\Zatca\Support\CertificateBuilder;
use KhaledHajSalem\Zatca\Services\ZatcaAPIService;
use KhaledHajSalem\Zatca\Exceptions\ZatcaException;

// Example: Sandbox onboarding
//
// 1. Generate a CSR and private key
// 2. Request a compliance certificate (CCSID) with the OTP
// 3. Exchange it for a production certificate (PCSID), which reporting and
//    clearance require
// 4. Save certificate.pem, private.pem and secret.txt in storage/
//
// The other examples read these files, so running this script refreshes the
// sandbox credentials for all of them. The developer-portal sandbox accepts
// the fixed OTP 123345 and issues the production certificate straight away.
// In simulation/production, get the OTP from the Fatoora portal of that
// environment and pass the compliance checks (validateInvoiceCompliance())
// before step 3.
//
// WARNING: this overwrites the files in storage/.

$storage = __DIR__ . '/../storage';

try {
    // 1. Generate CSR and private key
    $builder = new CertificateBuilder();
    $builder->setOrganizationIdentifier('399999999900003') // VAT number: 15 digits, starting and ending with 3
        ->setSerialNumber('ZatcaPHP', 'Example', 'SN001')    // Solution name, model, serial number
        ->setCommonName('TST-886431145-399999999900003')
        ->setCountryName('SA')                               // 2-character country code
        ->setOrganizationName('Maximum Speed Tech Supply LTD')
        ->setOrganizationalUnitName('Riyadh Branch')
        ->setAddress('RRRD2929')                             // Short national address
        ->setInvoiceType(1100)                               // 4 digits: [Standard][Simplified][future][future]
        ->setProduction(false)                               // false for sandbox/simulation, true for production
        ->setBusinessCategory('Supply activities');

    $builder->generateAndSave("$storage/certificate.csr", "$storage/private.pem");
    echo "CSR and private key saved to storage/\n";

    // 2. Request the compliance certificate
    $apiService = new ZatcaAPIService('sandbox');
    $compliance = $apiService->requestComplianceCertificate(file_get_contents("$storage/certificate.csr"), '123345');
    echo "Compliance certificate issued (request ID " . $compliance->getRequestId() . ")\n";

    // 3. Exchange it for the production certificate
    $production = $apiService->requestProductionCertificate(
        $compliance->getCertificate(),
        $compliance->getSecret(),
        $compliance->getRequestId()
    );

    // 4. Save the production certificate and its secret
    file_put_contents("$storage/certificate.pem", $production->getCertificate());
    file_put_contents("$storage/secret.txt", $production->getSecret());

    echo "Production certificate saved to storage/certificate.pem\n";
    echo "API secret saved to storage/secret.txt\n";

} catch (ZatcaException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
